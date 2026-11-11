@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit Log</h1>
            <p>Every change to records, and every attempt to open a restricted module.</p>
        </div>
    </div>

    <form class="card filters" method="GET">
        <div class="field">
            <label for="filter">Show</label>
            <select id="filter" name="filter" onchange="this.form.submit()">
                <option value="">All events</option>
                <option value="changes" @selected(request('filter') === 'changes')>Record changes only</option>
                <option value="denied" @selected(request('filter') === 'denied')>Denied access only</option>
            </select>
        </div>
        <noscript><button class="btn btn-outline" type="submit">Apply</button></noscript>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>When</th><th>User</th><th>Event</th><th>Description</th><th>IP address</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td style="white-space:nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}<br><small style="color:var(--muted)">{{ $log->user?->roleLabel() }}</small></td>
                    <td><span @class(['tag', 'tag-red' => $log->action === 'access.denied', 'tag-blue' => str_starts_with($log->action, 'auth.')])>{{ $log->action }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td class="mono">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No events recorded.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $logs->links() }}
    </div>
@endsection
