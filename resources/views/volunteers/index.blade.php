@extends('layouts.app')
@section('title', 'Volunteers')

@section('content')
    <div class="page-head">
        <div>
            <h1>Volunteer Registry</h1>
            <p>Everyone who helps run distributions, workshops, and relief operations.</p>
        </div>
        @can('volunteers.manage')
            <a class="btn" href="{{ route('volunteers.create') }}"><x-icon name="plus"/> Register Volunteer</a>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name or skill">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Any</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Volunteer</th><th>Contact</th><th>Skills</th><th>Availability</th><th class="num">Upcoming</th><th class="num">Completed</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($volunteers as $v)
                <tr>
                    <td><a class="row-link" href="{{ route('volunteers.show', $v) }}">{{ $v->last_name }}, {{ $v->first_name }}</a></td>
                    <td>{{ $v->phone ?: '—' }}<br><small style="color:var(--muted)">{{ $v->email }}</small></td>
                    <td>{{ $v->skills ?: '—' }}</td>
                    <td>{{ $v->availability ?: '—' }}</td>
                    <td class="num">{{ $v->upcoming_count }}</td>
                    <td class="num">{{ $v->completed_count }}</td>
                    <td><span @class(['tag', 'tag-green' => $v->status === 'active'])>{{ ucfirst($v->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No volunteers found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $volunteers->links() }}
    </div>
@endsection
