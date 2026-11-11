@extends('layouts.app')
@section('title', 'Distribution Records')

@section('content')
    <div class="page-head">
        <div>
            <h1>Relief Records List</h1>
            <p>Track distributions, the donations that funded them, and the beneficiaries reached.</p>
        </div>
        @can('distributions.manage')
            <a class="btn" href="{{ route('distributions.create') }}"><x-icon name="plus"/> Add New Entry</a>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Ledger ID, item, or activity">
        </div>
        <div class="field">
            <label for="program">Program</label>
            <select id="program" name="program">
                <option value="">All programs</option>
                @foreach ($programs as $p)
                    <option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="range">Date range</label>
            <select id="range" name="range">
                <option value="">All time</option>
                @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $days => $label)
                    <option value="{{ $days }}" @selected(request('range') == $days)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Record ID</th><th>Date</th><th>Funded by</th><th>Program / Activity</th><th>Item type</th><th class="num">Quantity</th><th class="num">Beneficiaries</th></tr></thead>
            <tbody>
            @forelse ($distributions as $d)
                <tr>
                    <td><a class="row-link mono" href="{{ route('distributions.show', $d) }}">{{ $d->reference_no }}</a></td>
                    <td>{{ $d->distributed_at->format('M j, Y') }}</td>
                    <td><strong>{{ $d->donations->map(fn ($x) => $x->donor->name)->unique()->join(', ') }}</strong></td>
                    <td>{{ $d->activity->title }}<br><small style="color:var(--muted)">{{ $d->activity->program->name }}</small></td>
                    <td>{{ $d->item_name }}</td>
                    <td class="num"><strong>{{ $d->formatQuantity() }}</strong></td>
                    <td class="num">{{ $d->beneficiaries_count }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No distributions found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $distributions->links() }}
    </div>
@endsection
