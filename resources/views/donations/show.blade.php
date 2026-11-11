@extends('layouts.app')
@section('title', $donation->reference_no)

@section('content')
    @php($used = $donation->usedAmount())
    @php($pct = $donation->quantity > 0 ? min(100, round($used / $donation->quantity * 100)) : 0)
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('donations.index') }}">Donations</a> / {{ $donation->reference_no }}</div>
            <h1>{{ $donation->reference_no }} · {{ $donation->description() }}</h1>
            <p>From <a href="{{ route('donors.show', $donation->donor) }}">{{ $donation->donor->name }}</a> on {{ $donation->received_at->format('F j, Y') }}</p>
        </div>
    </div>

    <div class="stats">
        <div class="card stat"><div class="stat-label">Donated</div><div class="stat-value">{{ $donation->formatAmount() }}</div></div>
        <div class="card stat"><div class="stat-label">Distributed</div><div class="stat-value">{{ $donation->formatAmount($used) }}</div>
            <div class="progress"><span style="width:{{ $pct }}%"></span></div></div>
        <div class="card stat"><div class="stat-label">Remaining balance</div><div class="stat-value">{{ $donation->formatAmount($donation->remaining()) }}</div></div>
    </div>

    <div class="grid-3-1">
        <div class="card">
            <div class="card-head"><h2>Where this donation went</h2><p>Distributions funded by {{ $donation->reference_no }}</p></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Ledger ID</th><th>Date</th><th>Activity</th><th>Item distributed</th><th class="num">Drawn</th><th class="num">Beneficiaries</th></tr></thead>
                <tbody>
                @forelse ($donation->fundings as $f)
                    <tr>
                        <td><a class="row-link mono" href="{{ route('distributions.show', $f->distribution) }}">{{ $f->distribution->reference_no }}</a></td>
                        <td>{{ $f->distribution->distributed_at->format('M j, Y') }}</td>
                        <td>{{ $f->distribution->activity->title }}</td>
                        <td>{{ $f->distribution->item_name }} ({{ $f->distribution->formatQuantity() }})</td>
                        <td class="num">{{ $donation->formatAmount((float) $f->amount) }}</td>
                        <td class="num">{{ $f->distribution->beneficiaries_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">This donation has not been used in any distribution yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card card-pad">
            <h2 style="margin-bottom:14px">Details</h2>
            <dl class="dl">
                <dt>Type</dt><dd>{{ \App\Models\Donation::TYPES[$donation->type] }}</dd>
                <dt>Program</dt><dd>{{ $donation->program->name }}</dd>
                <dt>Activity</dt><dd>{{ $donation->activity?->title ?? 'Program-wide' }}</dd>
                @unless ($donation->isCash())<dt>Unit</dt><dd>{{ $donation->unit?->name }}</dd>@endunless
                <dt>Recorded by</dt><dd>{{ $donation->recorder?->name ?? '—' }}</dd>
                <dt>Notes</dt><dd>{{ $donation->notes ?: '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection
