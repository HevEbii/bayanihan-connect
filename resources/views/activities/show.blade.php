@extends('layouts.app')
@section('title', $activity->title)

@section('content')
    @php($active = $activity->assignments->where('status', '!=', 'cancelled'))
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('programs.show', $activity->program) }}">{{ $activity->program->name }}</a> / Activity</div>
            <h1>{{ $activity->title }}</h1>
            <p>{{ $activity->activity_date->format('l, F j, Y') }} · {{ $activity->timeRange() }} · {{ $activity->location ?: 'Location TBA' }}</p>
        </div>
        <div class="actions">
            @can('distributions.manage')
                <a class="btn" href="{{ route('distributions.create', ['activity' => $activity->id]) }}"><x-icon name="box"/> Log Distribution</a>
            @endcan
            @can('programs.manage')
                <a class="btn btn-outline" href="{{ route('activities.edit', $activity) }}">Edit Activity</a>
            @endcan
        </div>
    </div>

    <div class="grid-3-1">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <h2>Volunteer Schedule</h2>
                    <p>{{ $active->count() }} of {{ $activity->volunteer_slots ?: 'unlimited' }} slots filled</p>
                </div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Volunteer</th><th>Task</th><th>Status</th>@can('volunteers.manage')<th class="num">Update</th>@endcan</tr></thead>
                    <tbody>
                    @forelse ($activity->assignments as $a)
                        <tr>
                            <td>
                                @can('volunteers.view')<a class="row-link" href="{{ route('volunteers.show', $a->volunteer) }}">{{ $a->volunteer->fullName() }}</a>@else{{ $a->volunteer->fullName() }}@endcan
                            </td>
                            <td>{{ $a->task ?: '—' }}</td>
                            <td><span @class(['tag', 'tag-blue' => $a->status === 'assigned', 'tag-green' => $a->status === 'completed', 'tag-red' => in_array($a->status, ['no_show', 'cancelled'])])>{{ ucfirst(str_replace('_', ' ', $a->status)) }}</span></td>
                            @can('volunteers.manage')
                                <td class="num">
                                    <form method="POST" action="{{ route('assignments.update', $a) }}" style="display:inline-flex;gap:6px">
                                        @csrf @method('PATCH')
                                        <select name="status" aria-label="Assignment status" style="min-height:32px;padding:4px 8px;width:auto">
                                            @foreach (\App\Models\VolunteerAssignment::STATUSES as $s)
                                                <option value="{{ $s }}" @selected($a->status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline" type="submit">Save</button>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No volunteers assigned yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Distributions at this Activity</h2></div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Ledger ID</th><th>Item</th><th class="num">Quantity</th><th class="num">Beneficiaries</th></tr></thead>
                    <tbody>
                    @forelse ($activity->distributions as $d)
                        <tr>
                            <td>@can('distributions.view')<a class="row-link mono" href="{{ route('distributions.show', $d) }}">{{ $d->reference_no }}</a>@else<span class="mono">{{ $d->reference_no }}</span>@endcan</td>
                            <td>{{ $d->item_name }}</td>
                            <td class="num">{{ $d->formatQuantity() }}</td>
                            <td class="num">{{ $d->beneficiaries_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Nothing distributed yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="stack">
            @can('volunteers.manage')
                @if ($activity->status === 'scheduled')
                    <form class="card card-pad" method="POST" action="{{ route('assignments.store') }}" novalidate>
                        @csrf
                        <input type="hidden" name="activity_id" value="{{ $activity->id }}">
                        <h2 style="margin-bottom:6px">Assign a Volunteer</h2>
                        <p class="field-hint" style="margin:0 0 14px">The system checks for clashes with the volunteer's other activities.</p>
                        <x-field name="volunteer_id" label="Volunteer" required style="margin-bottom:14px">
                            <select id="volunteer_id" name="volunteer_id" required @class(['is-invalid' => $errors->has('volunteer_id')])>
                                <option value="">Select volunteer…</option>
                                @foreach ($availableVolunteers as $v)
                                    <option value="{{ $v->id }}" @selected(old('volunteer_id') == $v->id)>{{ $v->fullName() }}{{ $v->skills ? ' — '.$v->skills : '' }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field name="task" label="Task" style="margin-bottom:16px">
                            <input id="task" type="text" name="task" value="{{ old('task') }}" maxlength="150" placeholder="e.g. Packing, Registration desk">
                        </x-field>
                        <button class="btn btn-block" type="submit">Confirm Assignment</button>
                    </form>
                @endif
            @endcan
            <div class="card card-pad">
                <h2 style="margin-bottom:14px">Details</h2>
                <dl class="dl">
                    <dt>Program</dt><dd>{{ $activity->program->name }}</dd>
                    <dt>Status</dt><dd>{{ ucfirst($activity->status) }}</dd>
                    <dt>Instructions</dt><dd>{{ $activity->description ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
