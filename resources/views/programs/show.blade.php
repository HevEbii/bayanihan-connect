@extends('layouts.app')
@section('title', $program->name)

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('programs.index') }}">Programs</a> / {{ $program->name }}</div>
            <h1>{{ $program->name }}</h1>
            <p>{{ $program->categoryLabel() }} · Coordinator: {{ $program->coordinator?->name ?? 'Unassigned' }}</p>
        </div>
        @can('programs.manage')
            <div class="actions">
                <a class="btn" href="{{ route('activities.create', ['program' => $program->id]) }}"><x-icon name="plus"/> Schedule Activity</a>
                <a class="btn btn-outline" href="{{ route('programs.edit', $program) }}">Edit Program</a>
            </div>
        @endcan
    </div>

    <div class="stats">
        <div class="card stat"><div class="stat-label">Activities</div><div class="stat-value">{{ $program->activities->count() }}</div></div>
        <div class="card stat"><div class="stat-label">Beneficiaries enrolled</div><div class="stat-value">{{ $program->beneficiaries_count }}</div></div>
        <div class="card stat"><div class="stat-label">Donations received</div><div class="stat-value">{{ $program->donations_count }}</div></div>
        <div class="card stat"><div class="stat-label">Status</div><div class="stat-value" style="font-size:1.2rem">{{ ucfirst(str_replace('_', ' ', $program->status)) }}</div>
            <div class="stat-hint">{{ $program->start_date?->format('M j, Y') ?? '—' }} to {{ $program->end_date?->format('M j, Y') ?? 'ongoing' }}</div></div>
    </div>

    @if ($program->description)
        <div class="card card-pad" style="margin-bottom:20px">{{ $program->description }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Activities</h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Activity</th><th>Date & time</th><th>Location</th><th class="num">Volunteers</th><th class="num">Distributions</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($program->activities as $a)
                <tr>
                    <td><a class="row-link" href="{{ route('activities.show', $a) }}">{{ $a->title }}</a></td>
                    <td>{{ $a->activity_date->format('D, M j, Y') }}<br><small style="color:var(--muted)">{{ $a->timeRange() }}</small></td>
                    <td>{{ $a->location ?: '—' }}</td>
                    <td class="num">{{ $a->assignments_count }}/{{ $a->volunteer_slots ?: '∞' }}</td>
                    <td class="num">{{ $a->distributions_count }}</td>
                    <td><span @class(['tag', 'tag-blue' => $a->status === 'scheduled', 'tag-green' => $a->status === 'completed', 'tag-red' => $a->status === 'cancelled'])>{{ ucfirst($a->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No activities scheduled yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endsection
