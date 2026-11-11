@extends('layouts.app')
@section('title', $volunteer->fullName())

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('volunteers.index') }}">Volunteers</a> / {{ $volunteer->fullName() }}</div>
            <h1>{{ $volunteer->fullName() }}</h1>
            <p>{{ $volunteer->skills ?: 'No skills listed' }} · {{ ucfirst($volunteer->status) }}</p>
        </div>
        <div class="actions">
            @if ($volunteer->user && Gate::allows('messages.use'))
                <a class="btn" href="{{ route('messages.show', $volunteer->user) }}"><x-icon name="message"/> Message</a>
            @endif
            @can('volunteers.manage')
                <a class="btn btn-outline" href="{{ route('volunteers.edit', $volunteer) }}">Edit</a>
            @endcan
        </div>
    </div>

    <div class="grid-3-1">
        <div class="card">
            <div class="card-head"><h2>Schedule & History</h2></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Activity</th><th>Task</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($volunteer->assignments as $a)
                    <tr>
                        <td>{{ $a->activity->activity_date->format('M j, Y') }}<br><small style="color:var(--muted)">{{ $a->activity->timeRange() }}</small></td>
                        <td><a class="row-link" href="{{ route('activities.show', $a->activity) }}">{{ $a->activity->title }}</a><br><small style="color:var(--muted)">{{ $a->activity->program->name }}</small></td>
                        <td>{{ $a->task ?: '—' }}</td>
                        <td><span @class(['tag', 'tag-blue' => $a->status === 'assigned', 'tag-green' => $a->status === 'completed', 'tag-red' => in_array($a->status, ['no_show', 'cancelled'])])>{{ ucfirst(str_replace('_', ' ', $a->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No assignments yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="stack">
            @can('volunteers.manage')
                @if ($volunteer->status === 'active')
                    <form class="card card-pad" method="POST" action="{{ route('assignments.store') }}" novalidate>
                        @csrf
                        <input type="hidden" name="volunteer_id" value="{{ $volunteer->id }}">
                        <h2 style="margin-bottom:14px">Assign to Activity</h2>
                        @error('volunteer_id')<div class="alert alert-error">{{ $message }}</div>@enderror
                        <x-field name="activity_id" label="Activity & time slot" required style="margin-bottom:14px">
                            <select id="activity_id" name="activity_id" required>
                                <option value="">Select activity…</option>
                                @foreach ($openActivities as $a)
                                    <option value="{{ $a->id }}" @selected(old('activity_id') == $a->id)>{{ $a->activity_date->format('M j') }} {{ $a->timeRange() }} · {{ $a->title }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field name="task" label="Task" style="margin-bottom:16px">
                            <input id="task" type="text" name="task" value="{{ old('task') }}" maxlength="150">
                        </x-field>
                        <button class="btn btn-block" type="submit">Confirm Assignment</button>
                    </form>
                @endif
            @endcan
            <div class="card card-pad">
                <h2 style="margin-bottom:14px">Contact</h2>
                <dl class="dl">
                    <dt>Mobile</dt><dd>{{ $volunteer->phone ?: '—' }}</dd>
                    <dt>Email</dt><dd>{{ $volunteer->email ?: '—' }}</dd>
                    <dt>Availability</dt><dd>{{ $volunteer->availability ?: '—' }}</dd>
                    <dt>App account</dt><dd>{{ $volunteer->user ? 'Yes ('.$volunteer->user->email.')' : 'No login yet' }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
