@extends('layouts.app')
@section('title', 'My Schedule')

@section('content')
    <div class="page-head">
        <div>
            <h1>My Schedule</h1>
            <p>Your upcoming activities and open slots you can sign up for.</p>
        </div>
    </div>

    <div class="grid-2">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>Upcoming</h2><p>{{ $upcoming->count() }} activit{{ $upcoming->count() === 1 ? 'y' : 'ies' }}</p></div>
                @forelse ($upcoming as $a)
                    <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                            <div>
                                <strong>{{ $a->activity->title }}</strong>
                                <div style="color:var(--muted);font-size:.88rem">{{ $a->activity->activity_date->format('l, M j') }} · {{ $a->activity->timeRange() }}</div>
                                <div style="font-size:.88rem">📍 {{ $a->activity->location ?: 'Location TBA' }}</div>
                                @if ($a->task)<div style="font-size:.88rem;margin-top:4px"><strong>Your task:</strong> {{ $a->task }}</div>@endif
                                @if ($a->activity->description)<div style="font-size:.86rem;margin-top:6px;color:var(--text-2)">{{ $a->activity->description }}</div>@endif
                            </div>
                            <form method="POST" action="{{ route('schedule.withdraw', $a) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Withdraw from {{ $a->activity->title }}?">Withdraw</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty">No upcoming activities yet. Pick one from the open slots.</div>
                @endforelse
            </div>

            <div class="card">
                <div class="card-head"><h2>History</h2></div>
                <div class="table-wrap"><table>
                    <tbody>
                    @forelse ($past as $a)
                        <tr>
                            <td>{{ $a->activity->activity_date->format('M j, Y') }}</td>
                            <td>{{ $a->activity->title }}</td>
                            <td><span @class(['tag', 'tag-green' => $a->status === 'completed'])>{{ ucfirst(str_replace('_', ' ', $a->status)) }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="empty">No past activities.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Open Slots</h2><p>Sign up for an activity</p></div>
            @forelse ($open as $activity)
                <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center">
                    <div>
                        <strong>{{ $activity->title }}</strong>
                        <div style="color:var(--muted);font-size:.86rem">{{ $activity->program->name }}</div>
                        <div style="font-size:.86rem">{{ $activity->activity_date->format('D, M j') }} · {{ $activity->timeRange() }} · {{ $activity->location ?: 'TBA' }}</div>
                        <small style="color:var(--muted)">{{ $activity->volunteer_slots ? ($activity->volunteer_slots - $activity->assignments_count).' slot(s) left' : 'Open to all' }}</small>
                    </div>
                    <form method="POST" action="{{ route('schedule.signup', $activity) }}">
                        @csrf
                        <button class="btn btn-sm btn-brand" type="submit">Sign Up</button>
                    </form>
                </div>
            @empty
                <div class="empty">No open slots right now. Check back soon!</div>
            @endforelse
        </div>
    </div>
@endsection
