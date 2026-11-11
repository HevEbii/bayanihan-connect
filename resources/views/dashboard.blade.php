@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard Overview</h1>
            <p>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ strtok($user->name, ' ') }}. Here's the current state of {{ $user->hasRole('volunteer') ? 'your volunteer work' : 'the Foundation\'s programs' }}.</p>
        </div>
    </div>

    <div class="stats">
        @foreach ($stats as $stat)
            <div class="card stat">
                <div class="stat-label">{{ $stat['label'] }}</div>
                <div class="stat-value">{{ $stat['value'] }}</div>
                <div class="stat-hint">{{ $stat['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid-2">
        <div class="stack">
            {{-- Shortcut cards keep every entry form within two steps of login (consultation revision). --}}
            <div class="card card-pad">
                <h2>Quick Operations</h2>
                <p style="color:var(--text-2);margin:6px 0 16px;font-size:.9rem">Go straight to the forms you use most.</p>
                <div style="display:grid;gap:10px">
                    @can('distributions.manage')
                        <a class="btn btn-block" href="{{ route('distributions.create') }}"><x-icon name="box"/> Log Distribution / Relief Record</a>
                    @endcan
                    @can('donations.manage')
                        <a class="btn btn-block" href="{{ route('donations.create') }}"><x-icon name="gift"/> Record New Donation</a>
                    @endcan
                    @can('beneficiaries.manage')
                        <a class="btn btn-block btn-outline" href="{{ route('beneficiaries.create') }}"><x-icon name="plus"/> Register Beneficiary</a>
                    @endcan
                    @can('volunteers.manage')
                        <a class="btn btn-block btn-outline" href="{{ route('programs.index') }}"><x-icon name="calendar"/> Schedule Volunteers</a>
                    @endcan
                    @can('schedule.own')
                        <a class="btn btn-block" href="{{ route('schedule.index') }}"><x-icon name="calendar"/> View My Schedule</a>
                    @endcan
                    @can('messages.use')
                        <a class="btn btn-block btn-outline" href="{{ route('messages.index') }}"><x-icon name="message"/> Open Messages</a>
                    @endcan
                    @can('reports.view')
                        <a class="btn btn-block btn-outline" href="{{ route('reports.index') }}"><x-icon name="chart"/> View Detailed Program Reports</a>
                    @endcan
                </div>
            </div>

            @isset($activities)
                <div class="card">
                    <div class="card-head"><h2>Upcoming Activities</h2><a href="{{ route('programs.index') }}" class="btn btn-sm btn-outline">All programs</a></div>
                    @forelse ($activities as $activity)
                        <a href="{{ route('activities.show', $activity) }}" class="card-body" style="display:flex;justify-content:space-between;gap:12px;text-decoration:none;border-bottom:1px solid var(--border);padding:14px 20px">
                            <span><strong>{{ $activity->title }}</strong><br><small style="color:var(--muted)">{{ $activity->program->name }} · {{ $activity->activity_date->format('D, M j') }} · {{ $activity->timeRange() }}</small></span>
                            <span class="tag {{ $activity->volunteer_slots && $activity->assignments_count >= $activity->volunteer_slots ? 'tag-green' : 'tag-amber' }}">{{ $activity->assignments_count }}/{{ $activity->volunteer_slots ?: '∞' }} volunteers</span>
                        </a>
                    @empty
                        <div class="empty">No upcoming activities in your programs.</div>
                    @endforelse
                </div>
            @endisset

            @isset($assignments)
                <div class="card">
                    <div class="card-head"><h2>My Next Assignments</h2></div>
                    @forelse ($assignments->take(5) as $a)
                        <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
                            <strong>{{ $a->activity->title }}</strong>
                            <div style="color:var(--muted);font-size:.86rem">{{ $a->activity->activity_date->format('l, M j') }} · {{ $a->activity->timeRange() }} · {{ $a->activity->location ?: 'Location TBA' }}</div>
                            @if ($a->task)<div style="font-size:.86rem;margin-top:4px">Task: {{ $a->task }}</div>@endif
                        </div>
                    @empty
                        <div class="empty">You have no upcoming assignments. <a href="{{ route('schedule.index') }}">Find an activity to join</a>.</div>
                    @endforelse
                </div>
            @endisset

            @isset($openDonations)
                <div class="card">
                    <div class="card-head"><h2>Donations With Remaining Balance</h2><a href="{{ route('donations.index') }}" class="btn btn-sm btn-outline">All donations</a></div>
                    <div class="table-wrap"><table>
                        <tbody>
                        @foreach ($openDonations as $d)
                            <tr>
                                <td><a class="row-link" href="{{ route('donations.show', $d) }}">{{ $d->reference_no }}</a><br><small style="color:var(--muted)">{{ $d->donor->name }}</small></td>
                                <td>{{ $d->description() }}<br><small style="color:var(--muted)">{{ $d->program->name }}</small></td>
                                <td class="num">{{ $d->formatAmount($d->remaining()) }}<br><small style="color:var(--muted)">of {{ $d->formatAmount() }}</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endisset
        </div>

        <div class="stack">
            <div class="card card-pad">
                <h2>Recent System Activity</h2>
                <ul class="feed" style="margin-top:8px">
                    @forelse ($recent as $log)
                        <li>
                            <strong>{{ $log->description }}</strong>
                            <small>By {{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</small>
                        </li>
                    @empty
                        <li class="empty" style="border:0">No activity yet.</li>
                    @endforelse
                </ul>
            </div>

            @isset($programs)
                <div class="card">
                    <div class="card-head"><h2>Active Programs</h2><a href="{{ route('programs.index') }}" class="btn btn-sm btn-outline">Manage</a></div>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Program</th><th class="num">Beneficiaries</th><th class="num">Activities</th></tr></thead>
                        <tbody>
                        @foreach ($programs as $program)
                            <tr>
                                <td><a class="row-link" href="{{ route('programs.show', $program) }}">{{ $program->name }}</a><br><small style="color:var(--muted)">{{ $program->categoryLabel() }}</small></td>
                                <td class="num">{{ $program->beneficiaries_count }}</td>
                                <td class="num">{{ $program->activities_count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endisset
        </div>
    </div>
@endsection
