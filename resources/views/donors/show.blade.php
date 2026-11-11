@extends('layouts.app')
@section('title', $donor->name)

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('donors.index') }}">Donor Registry</a> / {{ $donor->name }}</div>
            <h1>{{ $donor->name }}</h1>
            <p>{{ \App\Models\Donor::TYPES[$donor->type] ?? $donor->type }} · {{ $donor->donations->count() }} donation(s)</p>
        </div>
        <div class="actions">
            @can('donations.manage')
                <a class="btn" href="{{ route('donations.create', ['donor' => $donor->id]) }}"><x-icon name="plus"/> Record Donation</a>
            @endcan
            @can('donors.manage')
                <a class="btn btn-outline" href="{{ route('donors.edit', $donor) }}">Edit</a>
            @endcan
            <button class="btn btn-outline no-print" type="button" onclick="window.print()"><x-icon name="printer"/> Print impact summary</button>
        </div>
    </div>

    <div class="grid-3-1">
        <div class="card">
            <div class="card-head"><h2>Donor Impact Summary</h2><p>How each contribution was used</p></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Reference</th><th>Donation</th><th>Program</th><th>Used in</th><th class="num">Remaining</th></tr></thead>
                <tbody>
                @forelse ($donor->donations as $d)
                    <tr>
                        <td><a class="row-link mono" href="{{ route('donations.show', $d) }}">{{ $d->reference_no }}</a><br><small style="color:var(--muted)">{{ $d->received_at->format('M j, Y') }}</small></td>
                        <td>{{ $d->description() }}<br><strong>{{ $d->formatAmount() }}</strong></td>
                        <td>{{ $d->program->name }}</td>
                        <td>
                            @forelse ($d->distributions as $dist)
                                <div style="font-size:.86rem">{{ $dist->activity->title }} — {{ $d->formatAmount((float) $dist->pivot->amount) }}</div>
                            @empty
                                <span style="color:var(--muted)">Not yet distributed</span>
                            @endforelse
                        </td>
                        <td class="num">
                            {{ $d->formatAmount($d->remaining()) }}
                            @php($pct = $d->quantity > 0 ? min(100, round($d->usedAmount() / $d->quantity * 100)) : 0)
                            <div class="progress" title="{{ $pct }}% used"><span style="width:{{ $pct }}%"></span></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No donations recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card card-pad">
            <h2 style="margin-bottom:14px">Contact</h2>
            <dl class="dl">
                <dt>Contact person</dt><dd>{{ $donor->contact_person ?: '—' }}</dd>
                <dt>Email</dt><dd>{{ $donor->email ?: '—' }}</dd>
                <dt>Phone</dt><dd>{{ $donor->phone ?: '—' }}</dd>
                <dt>Address</dt><dd>{{ $donor->address ?: '—' }}</dd>
                <dt>Donor portal</dt><dd>{{ $donor->user_id ? 'Has a view-only login' : 'No login' }}</dd>
            </dl>
        </div>
    </div>
@endsection
