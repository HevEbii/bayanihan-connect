@extends('layouts.app')
@section('title', 'My Impact')

@section('content')
    <div class="page-head">
        <div>
            <h1>Thank you, {{ $donor->name }}</h1>
            <p>Here's exactly where your contributions went. This page updates automatically as goods are distributed.</p>
        </div>
        <button class="btn btn-outline no-print" type="button" onclick="window.print()"><x-icon name="printer"/> Print / Save PDF</button>
    </div>

    <div class="stats">
        <div class="card stat"><div class="stat-label">Cash donated</div><div class="stat-value">₱{{ number_format($totals['cash'], 2) }}</div></div>
        <div class="card stat"><div class="stat-label">In-kind donations</div><div class="stat-value">{{ $totals['in_kind'] }}</div></div>
        <div class="card stat"><div class="stat-label">Programs supported</div><div class="stat-value">{{ $totals['programs'] }}</div></div>
        <div class="card stat"><div class="stat-label">Beneficiaries reached</div><div class="stat-value">{{ number_format($totals['beneficiaries']) }}</div>
            <div class="stat-hint">Through distributions you helped fund</div></div>
    </div>

    <div class="stack">
        @forelse ($donations as $d)
            @php($pct = $d->quantity > 0 ? min(100, round($d->usedAmount() / $d->quantity * 100)) : 0)
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>{{ $d->description() }} — {{ $d->formatAmount() }}</h2>
                        <p>{{ $d->reference_no }} · Received {{ $d->received_at->format('M j, Y') }} · {{ $d->program->name }}</p>
                    </div>
                    <div style="min-width:160px;text-align:right">
                        <small style="color:var(--muted)">{{ $pct }}% distributed</small>
                        <div class="progress"><span style="width:{{ $pct }}%"></span></div>
                    </div>
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Date</th><th>Activity</th><th>Goods distributed</th><th class="num">From your donation</th><th class="num">Beneficiaries</th></tr></thead>
                    <tbody>
                    @forelse ($d->fundings as $f)
                        <tr>
                            <td>{{ $f->distribution->distributed_at->format('M j, Y') }}</td>
                            <td>{{ $f->distribution->activity->title }}<br><small style="color:var(--muted)">{{ $f->distribution->activity->location }}</small></td>
                            <td>{{ $f->distribution->formatQuantity() }} {{ $f->distribution->item_name }}</td>
                            <td class="num">{{ $d->formatAmount((float) $f->amount) }}</td>
                            <td class="num">{{ $f->distribution->beneficiaries_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Not yet distributed. It is reserved for {{ $d->activity?->title ?? $d->program->name }}.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        @empty
            <div class="card empty">No donations are linked to your account yet.</div>
        @endforelse
    </div>
@endsection
