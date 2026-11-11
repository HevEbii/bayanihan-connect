@extends('layouts.app')
@section('title', 'Reports')

@section('content')
    @php
        $from = old('from', request('from', today()->startOfMonth()->format('Y-m-d')));
        $to = old('to', request('to', today()->format('Y-m-d')));
        $selected = collect(old('programs', request('programs', [])))->map(fn ($id) => (int) $id);
    @endphp
    <div class="page-head no-print">
        <div>
            <h1>Consolidated Program Report</h1>
            <p>Compile donations, distributions, beneficiaries, and volunteer participation across programs.</p>
        </div>
    </div>

    <form class="card card-body no-print" method="GET" action="{{ route('reports.index') }}" style="margin-bottom:20px" novalidate>
        <div class="form-grid-3">
            <x-field name="from" label="From" required>
                <input id="from" type="date" name="from" value="{{ $from }}" required @class(['is-invalid' => $errors->has('from')])>
            </x-field>
            <x-field name="to" label="To" required>
                <input id="to" type="date" name="to" value="{{ $to }}" required @class(['is-invalid' => $errors->has('to')])>
            </x-field>
            <div class="field">
                <span class="label">Quick ranges</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    @foreach (['This month' => [today()->startOfMonth(), today()], 'Last 90 days' => [today()->subDays(90), today()], 'This year' => [today()->startOfYear(), today()]] as $label => [$a, $b])
                        <a class="btn btn-sm btn-outline" href="{{ route('reports.index', ['from' => $a->format('Y-m-d'), 'to' => $b->format('Y-m-d'), 'programs' => $selected->all()]) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="field" style="margin-top:16px">
            <span class="label">Programs <small style="text-transform:none;letter-spacing:0;font-weight:400">(leave all unticked for every program)</small></span>
            <div style="display:flex;flex-wrap:wrap;gap:8px 18px">
                @foreach ($programs as $p)
                    <label class="check"><input type="checkbox" name="programs[]" value="{{ $p->id }}" @checked($selected->contains($p->id))> {{ $p->name }}</label>
                @endforeach
            </div>
        </div>
        <div class="form-foot">
            @if ($report)
                <a class="btn btn-outline" href="{{ route('reports.export', request()->query()) }}"><x-icon name="download"/> Export CSV</a>
                <button class="btn btn-outline" type="button" onclick="window.print()"><x-icon name="printer"/> Print / Save PDF</button>
            @endif
            <button class="btn" type="submit"><x-icon name="chart"/> Generate Report</button>
        </div>
    </form>

    @if ($report)
        <div class="report-head">
            <h1>Bayanihan Outreach Foundation — Consolidated Program Report</h1>
            <p>{{ $report['from']->format('F j, Y') }} to {{ $report['to']->format('F j, Y') }} · {{ $selected->isEmpty() ? 'All programs' : $report['programs']->pluck('name')->join(', ') }} · Generated {{ now()->format('M j, Y g:i A') }}</p>
        </div>

        @unless ($report['has_data'])
            <div class="alert alert-warning">No donations, distributions, or volunteer activity were recorded for {{ $report['from']->format('M j, Y') }} to {{ $report['to']->format('M j, Y') }}. Try a wider date range or different programs.</div>
        @else
            <div class="stats">
                <div class="card stat"><div class="stat-label">Cash received</div><div class="stat-value">₱{{ number_format($report['totals']['cash_received'], 2) }}</div>
                    <div class="stat-hint">{{ $report['totals']['donations'] }} donations from {{ $report['totals']['donors'] }} donors</div></div>
                <div class="card stat"><div class="stat-label">Beneficiaries served</div><div class="stat-value">{{ number_format($report['totals']['beneficiaries_served']) }}</div>
                    <div class="stat-hint">Unique people across {{ $report['totals']['distributions'] }} distributions</div></div>
                <div class="card stat"><div class="stat-label">Volunteers</div><div class="stat-value">{{ $report['totals']['volunteers'] }}</div>
                    <div class="stat-hint">{{ $report['totals']['volunteer_hours'] }} hours completed</div></div>
            </div>

            <div class="card" style="margin-bottom:20px">
                <div class="card-head"><h2>By Program</h2></div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Program</th><th class="num">Cash received</th><th class="num">In-kind donations</th><th class="num">Distributions</th><th class="num">Beneficiaries served</th><th class="num">Volunteers</th></tr></thead>
                    <tbody>
                    @foreach ($report['by_program'] as $row)
                        <tr>
                            <td><strong>{{ $row['program']->name }}</strong><br><small style="color:var(--muted)">{{ $row['program']->categoryLabel() }}</small></td>
                            <td class="num">₱{{ number_format($row['cash'], 2) }}</td>
                            <td class="num">{{ $row['in_kind_count'] }}</td>
                            <td class="num">{{ $row['distributions'] }}</td>
                            <td class="num">{{ $row['beneficiaries'] }}</td>
                            <td class="num">{{ $row['volunteers'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>

            <div class="grid-2">
                @foreach (['goods_received' => 'Goods Received (in-kind)', 'goods_distributed' => 'Goods Distributed'] as $key => $title)
                    <div class="card card-pad">
                        <h2 style="margin-bottom:12px">{{ $title }}</h2>
                        @php $max = max(1, $report[$key]->max('quantity') ?? 1); @endphp
                        @forelse ($report[$key] as $g)
                            <div class="bar-row">
                                <span>{{ $g['item'] }}</span>
                                <span class="bar"><span style="width:{{ round($g['quantity'] / $max * 100) }}%"></span></span>
                                <span class="num" style="text-align:right">{{ \App\Models\Donation::trimNumber($g['quantity']) }} <small style="color:var(--muted)">{{ $g['unit'] }}</small></span>
                            </div>
                        @empty
                            <p style="color:var(--muted)">None in this period.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endunless
    @endif
@endsection
