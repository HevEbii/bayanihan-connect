@extends('layouts.app')
@section('title', 'Log Distribution')

@section('content')
    @php
        $fundingRows = old('funding', [['donation_id' => '', 'amount' => '']]);
        $selectedBeneficiaries = collect(old('beneficiaries', []))->map(fn ($id) => (int) $id);
        $donationData = $donations->map(fn ($d) => [
            'id' => $d->id, 'ref' => $d->reference_no, 'donor' => $d->donor->name, 'program' => $d->program_id,
            'cash' => $d->isCash(), 'item' => $d->description(), 'unit' => $d->unit_id, 'unitName' => $d->unit?->name,
            'remaining' => $d->remaining(), 'remainingLabel' => $d->formatAmount($d->remaining()),
        ])->values();
    @endphp

    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('distributions.index') }}">Distribution</a> / New</div>
            <h1>New Relief / Distribution Record</h1>
            <p>Log goods handed out at an activity, the donation(s) that funded them, and the beneficiaries served.</p>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ route('distributions.store') }}" id="distribution-form"
          data-offline="Distribution" data-offline-label="#item_name" novalidate>
        @csrf
        <input type="hidden" name="client_uuid" data-client-uuid value="{{ old('client_uuid') }}">

        <h2 style="margin-bottom:14px">1. What was distributed</h2>
        <div class="form-grid">
            <x-field name="activity_id" label="Activity / relief program" required class="span-2">
                <select id="activity_id" name="activity_id" required @class(['is-invalid' => $errors->has('activity_id')])>
                    <option value="">Select activity…</option>
                    @foreach ($activities->groupBy(fn ($a) => $a->program->name) as $programName => $group)
                        <optgroup label="{{ $programName }}">
                            @foreach ($group as $a)
                                <option value="{{ $a->id }}" data-program="{{ $a->program_id }}" @selected(old('activity_id', $selectedActivity) == $a->id)>{{ $a->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-field>
            <x-field name="item_name" label="Relief item / distribution type" required class="span-2">
                <input id="item_name" type="text" name="item_name" value="{{ old('item_name') }}" maxlength="150" required placeholder="e.g. Rice, Canned Goods, Family Food Packs"
                       @class(['is-invalid' => $errors->has('item_name')])>
            </x-field>
        </div>
        <div class="form-grid-3" style="margin-top:18px">
            <x-field name="quantity" label="Amount / quantity" required>
                <input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0.01" step="0.01" required placeholder="e.g. 500"
                       @class(['is-invalid' => $errors->has('quantity')])>
            </x-field>
            <x-field name="unit_id" label="Unit" required>
                <select id="unit_id" name="unit_id" required @class(['is-invalid' => $errors->has('unit_id')])>
                    <option value="">Select a unit…</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="distributed_at" label="Date" required>
                <input id="distributed_at" type="date" name="distributed_at" value="{{ old('distributed_at', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required>
            </x-field>
        </div>

        <h2 style="margin:28px 0 6px">2. Funded by</h2>
        <p class="field-hint" style="margin:0 0 14px">Draw from one or more donations assigned to this activity's program. In-kind goods must be in the same unit.</p>
        @error('funding')<div class="alert alert-error">{{ $message }}</div>@enderror
        <div id="funding-lines">
            @foreach ($fundingRows as $i => $row)
                <div class="funding-line" data-line>
                    <div>
                        <select name="funding[{{ $i }}][donation_id]" data-donation aria-label="Funding donation"
                                @class(['is-invalid' => $errors->has("funding.$i.donation_id")])>
                            <option value="">Select donation…</option>
                            @foreach ($donationData as $d)
                                <option value="{{ $d['id'] }}" @selected(($row['donation_id'] ?? '') == $d['id'])>{{ $d['ref'] }} · {{ $d['donor'] }} · {{ $d['item'] }} ({{ $d['remainingLabel'] }} left)</option>
                            @endforeach
                        </select>
                        <div class="funding-meta" data-meta></div>
                        @error("funding.$i.donation_id")<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <input type="number" name="funding[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" min="0.01" step="0.01" placeholder="Amount drawn"
                               aria-label="Amount drawn" data-amount @class(['is-invalid' => $errors->has("funding.$i.amount")])>
                        @error("funding.$i.amount")<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <button type="button" class="icon-btn" data-remove aria-label="Remove funding line">×</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline" id="add-funding"><x-icon name="plus" :size="14"/> Add another funding source</button>

        <h2 style="margin:28px 0 6px">3. Beneficiaries served</h2>
        <p class="field-hint" style="margin:0 0 10px">Tick each family or learner who received goods. Only beneficiaries of the selected program are listed. <span id="ben-count"></span></p>
        <input type="search" id="ben-search" placeholder="Search by name or ID" style="margin-bottom:8px" aria-label="Search beneficiaries">
        <div class="pick-list" id="ben-list">
            @foreach ($beneficiaries as $b)
                <label data-program="{{ $b->program_id }}" data-search="{{ strtolower($b->code.' '.$b->first_name.' '.$b->last_name) }}">
                    <input type="checkbox" name="beneficiaries[]" value="{{ $b->id }}" @checked($selectedBeneficiaries->contains($b->id))>
                    <span>{{ $b->last_name }}, {{ $b->first_name }} <small>· {{ $b->code }} · {{ $b->barangay }}</small></span>
                </label>
            @endforeach
            <div class="empty" id="ben-empty" hidden>Select an activity to list its program's beneficiaries.</div>
        </div>

        <div style="margin-top:22px">
            <x-field name="notes" label="Internal remarks / notes">
                <textarea id="notes" name="notes" maxlength="2000" placeholder="Optional instructions or remarks concerning delivery logistics…">{{ old('notes') }}</textarea>
            </x-field>
        </div>

        <div class="form-foot">
            <a class="btn btn-outline" href="{{ route('distributions.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Entry Record</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const donations = @json($donationData);
        const byId = Object.fromEntries(donations.map((d) => [String(d.id), d]));
        const activity = document.getElementById('activity_id');
        const unit = document.getElementById('unit_id');
        const lines = document.getElementById('funding-lines');
        const benList = document.getElementById('ben-list');
        const benSearch = document.getElementById('ben-search');
        let counter = lines.querySelectorAll('[data-line]').length;

        const programId = () => activity.selectedOptions[0]?.dataset.program || '';

        function refreshLine(line) {
            const select = line.querySelector('[data-donation]');
            const meta = line.querySelector('[data-meta]');
            const amount = line.querySelector('[data-amount]');
            [...select.options].forEach((o) => {
                if (!o.value) return;
                const d = byId[o.value];
                const wrongProgram = programId() && String(d.program) !== programId();
                const wrongUnit = !d.cash && unit.value && String(d.unit) !== unit.value;
                o.hidden = wrongProgram || wrongUnit;
                o.disabled = o.hidden;
            });
            if (select.selectedOptions[0]?.disabled) select.value = '';
            const d = byId[select.value];
            meta.textContent = d ? (d.cash ? `Cash · ${d.remainingLabel} available (amount in ₱)` : `In-kind · ${d.remainingLabel} available`) : '';
            amount.max = d ? d.remaining : '';
            amount.placeholder = d ? (d.cash ? 'Pesos drawn' : `${d.unitName} drawn`) : 'Amount drawn';
        }
        function refreshAll() { lines.querySelectorAll('[data-line]').forEach(refreshLine); filterBeneficiaries(); }

        function filterBeneficiaries() {
            const p = programId();
            const term = benSearch.value.trim().toLowerCase();
            let visible = 0;
            benList.querySelectorAll('label').forEach((l) => {
                const show = p && l.dataset.program === p && (!term || l.dataset.search.includes(term));
                l.hidden = !show && !l.querySelector('input').checked;
                if (!l.hidden) visible++;
            });
            document.getElementById('ben-empty').hidden = visible > 0;
            document.getElementById('ben-empty').textContent = p ? 'No beneficiaries enrolled in this program match.' : 'Select an activity to list its program\'s beneficiaries.';
            document.getElementById('ben-count').textContent = `${benList.querySelectorAll('input:checked').length} selected.`;
        }

        document.getElementById('add-funding').addEventListener('click', () => {
            const clone = lines.querySelector('[data-line]').cloneNode(true);
            clone.querySelectorAll('.field-error').forEach((e) => e.remove());
            clone.querySelectorAll('.is-invalid').forEach((e) => e.classList.remove('is-invalid'));
            clone.querySelector('[data-donation]').name = `funding[${counter}][donation_id]`;
            clone.querySelector('[data-donation]').value = '';
            clone.querySelector('[data-amount]').name = `funding[${counter}][amount]`;
            clone.querySelector('[data-amount]').value = '';
            counter++;
            lines.appendChild(clone);
            refreshLine(clone);
        });
        lines.addEventListener('click', (e) => {
            if (!e.target.closest('[data-remove]')) return;
            const all = lines.querySelectorAll('[data-line]');
            const line = e.target.closest('[data-line]');
            if (all.length > 1) line.remove(); else { line.querySelector('[data-donation]').value = ''; line.querySelector('[data-amount]').value = ''; refreshLine(line); }
        });
        lines.addEventListener('change', (e) => { if (e.target.matches('[data-donation]')) refreshLine(e.target.closest('[data-line]')); });
        activity.addEventListener('change', refreshAll);
        unit.addEventListener('change', refreshAll);
        benSearch.addEventListener('input', filterBeneficiaries);
        benList.addEventListener('change', filterBeneficiaries);
        document.getElementById('distribution-form').addEventListener('offline-saved', refreshAll);
        refreshAll();
    })();
</script>
@endpush
