@extends('layouts.app')
@section('title', 'Record Donation')

@section('content')
    @php($type = old('type', 'in_kind'))
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('donations.index') }}">Donations</a> / New</div>
            <h1>New Donation Record</h1>
            <p>Record the donation and link it to the project it is meant for. A reference number is assigned on save.</p>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ route('donations.store') }}" id="donation-form" novalidate>
        @csrf
        <div class="form-grid">
            <x-field name="donor_id" label="Donor" required>
                <select id="donor_id" name="donor_id" required @class(['is-invalid' => $errors->has('donor_id')])>
                    <option value="">Select donor…</option>
                    @foreach ($donors as $donor)
                        <option value="{{ $donor->id }}" @selected(old('donor_id', $selectedDonor) == $donor->id)>{{ $donor->name }}</option>
                    @endforeach
                </select>
                @can('donors.manage')
                    <div class="field-hint">Not listed? <a href="{{ route('donors.create', ['return_to_donation' => 1]) }}">Add a new donor</a></div>
                @endcan
            </x-field>
            <x-field name="received_at" label="Date received" required>
                <input id="received_at" type="date" name="received_at" value="{{ old('received_at', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required
                       @class(['is-invalid' => $errors->has('received_at')])>
            </x-field>

            <div class="field span-2">
                <span class="label">Donation type <span class="req">*</span></span>
                <div class="segmented" role="radiogroup">
                    @foreach (\App\Models\Donation::TYPES as $key => $label)
                        <label><input type="radio" name="type" value="{{ $key }}" @checked($type === $key)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>

            <x-field name="item_name" label="Relief item / goods" required class="span-2 in-kind-only">
                {{-- Type any goods name, or pick a suggestion from the list (click the field or start typing). --}}
                <input id="item_name" type="text" name="item_name" value="{{ old('item_name') }}" maxlength="150" list="item-suggestions" autocomplete="off"
                       placeholder="Type or choose, e.g. Rice, Canned Goods, Bottled Water"
                       @class(['is-invalid' => $errors->has('item_name')])>
                <datalist id="item-suggestions">
                    @foreach ($itemSuggestions as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>
            </x-field>

            <x-field name="quantity" label="Amount / quantity" required>
                {{-- The arrows step by 1,000 for cash and by 10 for goods (set in the script below); any number can still be typed, including decimals. --}}
                <input id="quantity" type="number" name="quantity" value="{{ old('quantity') }}" min="0" step="10" required placeholder="e.g. 500"
                       @class(['is-invalid' => $errors->has('quantity')])>
                <div class="field-hint cash-only">Amount in Philippine pesos (PHP).</div>
            </x-field>
            {{-- FR05: in-kind goods must use one standardized unit from this list. --}}
            <x-field name="unit_id" label="Unit" required class="in-kind-only" hint="Count goods in the unit they will be distributed in, so balances match.">
                <select id="unit_id" name="unit_id" @class(['is-invalid' => $errors->has('unit_id')])>
                    <option value="">Select a unit…</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="program_id" label="Project / program" required>
                <select id="program_id" name="program_id" required @class(['is-invalid' => $errors->has('program_id')])>
                    <option value="">Select program…</option>
                    @foreach ($programs as $p)
                        <option value="{{ $p->id }}" @selected(old('program_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="activity_id" label="Specific activity" hint="Optional. Leave blank if the donation is for the program in general.">
                <select id="activity_id" name="activity_id" @class(['is-invalid' => $errors->has('activity_id')])>
                    <option value="">Program-wide</option>
                    @foreach ($activities as $a)
                        <option value="{{ $a->id }}" data-program="{{ $a->program_id }}" @selected(old('activity_id') == $a->id)>{{ $a->label() }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="notes" label="Internal remarks / notes" class="span-2">
                <textarea id="notes" name="notes" maxlength="2000" placeholder="Optional instructions or remarks concerning delivery logistics…">{{ old('notes') }}</textarea>
            </x-field>
        </div>

        <div class="form-foot">
            <a class="btn btn-outline" href="{{ route('donations.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Donation</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('donation-form');
        const program = document.getElementById('program_id');
        const activity = document.getElementById('activity_id');

        function syncType() {
            const isCash = form.querySelector('input[name=type]:checked')?.value === 'cash';
            form.querySelectorAll('.in-kind-only').forEach((el) => { el.hidden = isCash; });
            form.querySelectorAll('.cash-only').forEach((el) => { el.hidden = !isCash; });
            // Cash is an amount in pesos; goods are a quantity in a unit.
            const quantityLabel = form.querySelector('label[for="quantity"]');
            if (quantityLabel) quantityLabel.firstChild.nodeValue = isCash ? 'Amount (PHP) ' : 'Quantity ';
            document.getElementById('quantity').step = isCash ? 1000 : 10;
            document.getElementById('unit_id').required = !isCash;
            document.getElementById('item_name').required = !isCash;
        }
        function syncActivities() {
            [...activity.options].forEach((o) => { if (o.value) o.hidden = program.value && o.dataset.program !== program.value; });
            if (activity.selectedOptions[0]?.hidden) activity.value = '';
        }
        form.querySelectorAll('input[name=type]').forEach((r) => r.addEventListener('change', syncType));
        program.addEventListener('change', syncActivities);

        // Choosing or typing a known item selects the unit that normally goes with it (until you pick a unit yourself).
        const itemUnits = @json($itemUnits);
        const item = document.getElementById('item_name');
        const unit = document.getElementById('unit_id');
        let unitChosenByUser = unit.value !== '';
        unit.addEventListener('change', (e) => { if (e.isTrusted) unitChosenByUser = true; });
        function suggestUnit() {
            const id = itemUnits[item.value.trim().toLowerCase()];
            if (id && !unitChosenByUser) unit.value = String(id);
        }
        item.addEventListener('input', suggestUnit);
        item.addEventListener('change', suggestUnit);

        syncType();
        syncActivities();
    })();
</script>
@endpush
