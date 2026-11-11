@extends('layouts.app')
@section('title', 'Programs & Activities')

@section('content')
    <div class="page-head">
        <div>
            <h1>Programs & Activities</h1>
            <p>Food distribution, education, workshops, relief, and community projects.</p>
        </div>
        @can('programs.manage')
            <div class="actions">
                <a class="btn btn-outline" href="{{ route('activities.create') }}"><x-icon name="calendar"/> Schedule Activity</a>
                <a class="btn" href="{{ route('programs.create') }}"><x-icon name="plus"/> New Program</a>
            </div>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach (\App\Models\Program::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Any status</option>
                @foreach (\App\Models\Program::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Program</th><th>Category</th><th>Coordinator</th><th class="num">Activities</th><th class="num">Beneficiaries</th><th class="num">Donations</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($programs as $p)
                <tr>
                    <td><a class="row-link" href="{{ route('programs.show', $p) }}">{{ $p->name }}</a></td>
                    <td>{{ $p->categoryLabel() }}</td>
                    <td>{{ $p->coordinator?->name ?? '—' }}</td>
                    <td class="num">{{ $p->activities_count }}</td>
                    <td class="num">{{ $p->beneficiaries_count }}</td>
                    <td class="num">{{ $p->donations_count }}</td>
                    <td><span @class(['tag', 'tag-green' => $p->status === 'active', 'tag-amber' => $p->status === 'on_hold'])>{{ ucfirst(str_replace('_', ' ', $p->status)) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No programs yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endsection
