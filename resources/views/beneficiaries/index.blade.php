@extends('layouts.app')
@section('title', 'Beneficiaries')

@section('content')
    <div class="page-head">
        <div>
            <h1>Beneficiary Registry</h1>
            <p>One shared list of families and learners served across all programs.</p>
        </div>
        @can('beneficiaries.manage')
            <a class="btn" href="{{ route('beneficiaries.create') }}"><x-icon name="plus"/> Register Beneficiary</a>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name, ID, or barangay">
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
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach (\App\Models\Beneficiary::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Beneficiary ID</th><th>Name</th><th>Barangay</th><th>Category</th><th>Program</th><th class="num">Household</th><th>Last updated</th></tr>
                </thead>
                <tbody>
                @forelse ($beneficiaries as $b)
                    <tr>
                        <td class="mono">{{ $b->code }}</td>
                        <td><a class="row-link" href="{{ route('beneficiaries.show', $b) }}">{{ $b->last_name }}, {{ $b->first_name }}</a></td>
                        <td>{{ $b->barangay }}</td>
                        <td><span class="tag">{{ $b->categoryLabel() }}</span></td>
                        <td>{{ $b->program?->name ?? '—' }}</td>
                        <td class="num">{{ $b->household_size }}</td>
                        <td>{{ $b->updated_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No beneficiaries match your filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $beneficiaries->links() }}
    </div>
@endsection
