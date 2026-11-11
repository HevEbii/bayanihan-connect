@extends('layouts.app')
@section('title', 'Donor Registry')

@section('content')
    <div class="page-head">
        <div>
            <h1>Donor Registry</h1>
            <p>Individuals, institutions, and partner organizations supporting the Foundation.</p>
        </div>
        @can('donors.manage')
            <a class="btn" href="{{ route('donors.create') }}"><x-icon name="plus"/> Add Donor</a>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Donor name">
        </div>
        <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach (\App\Models\Donor::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Donor</th><th>Type</th><th>Contact</th><th class="num">Donations</th><th class="num">Cash given</th><th>Portal</th></tr></thead>
            <tbody>
            @forelse ($donors as $donor)
                <tr>
                    <td><a class="row-link" href="{{ route('donors.show', $donor) }}">{{ $donor->name }}</a></td>
                    <td><span class="tag">{{ \App\Models\Donor::TYPES[$donor->type] ?? $donor->type }}</span></td>
                    <td>{{ $donor->contact_person ?: '—' }}<br><small style="color:var(--muted)">{{ $donor->email ?: $donor->phone }}</small></td>
                    <td class="num">{{ $donor->donations_count }}</td>
                    <td class="num">₱{{ number_format($donor->cash_total ?? 0, 2) }}</td>
                    <td>@if ($donor->user_id)<span class="tag tag-green">Active</span>@else<span class="tag">None</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No donors found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $donors->links() }}
    </div>
@endsection
