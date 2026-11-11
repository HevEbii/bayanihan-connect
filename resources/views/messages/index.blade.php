@extends('layouts.app')
@section('title', 'Messages')

@section('content')
    <div class="page-head">
        <div>
            <h1>Messages</h1>
            <p>Coordinate activities with coordinators and volunteers without leaving Bayanihan Connect.</p>
        </div>
    </div>

    <div class="grid-3-1">
        <div class="card convo-list">
            <div class="card-head"><h2>Conversations</h2></div>
            @forelse ($conversations as $c)
                <a href="{{ route('messages.show', $c['partner']) }}">
                    <span class="avatar"><x-icon name="user" :size="16"/></span>
                    <span style="min-width:0;flex:1">
                        <strong>{{ $c['partner']->name }}</strong> <small style="color:var(--muted)">· {{ $c['partner']->roleLabel() }}</small>
                        <div class="preview">{{ (int) $c['last']->sender_id === auth()->id() ? 'You: ' : '' }}{{ $c['last']->body }}</div>
                    </span>
                    <span class="meta">
                        {{ $c['last']->created_at->diffForHumans(short: true) }}
                        @if ($c['unread'])<br><span class="badge-count">{{ $c['unread'] }}</span>@endif
                    </span>
                </a>
            @empty
                <div class="empty">No messages yet. Start a conversation with the form.</div>
            @endforelse
        </div>

        <form class="card card-pad" method="POST" action="{{ route('messages.store') }}" data-offline="Message" data-offline-label="#recipient_id" novalidate>
            @csrf
            <input type="hidden" name="client_uuid" data-client-uuid>
            <h2 style="margin-bottom:14px">New Message</h2>
            <x-field name="recipient_id" label="To" required style="margin-bottom:14px">
                <select id="recipient_id" name="recipient_id" required @class(['is-invalid' => $errors->has('recipient_id')])>
                    <option value="">Choose coordinator or volunteer…</option>
                    @foreach ($recipients->groupBy(fn ($u) => $u->roleLabel()) as $role => $group)
                        <optgroup label="{{ $role }}">
                            @foreach ($group as $r)
                                <option value="{{ $r->id }}" @selected(old('recipient_id') == $r->id)>{{ $r->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-field>
            <x-field name="activity_id" label="About activity (optional)" style="margin-bottom:14px">
                <select id="activity_id" name="activity_id">
                    <option value="">None</option>
                    @foreach ($activities as $a)
                        <option value="{{ $a->id }}" @selected(old('activity_id') == $a->id)>{{ $a->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="body" label="Message" required style="margin-bottom:16px">
                <textarea id="body" name="body" maxlength="5000" required placeholder="Write your message…" @class(['is-invalid' => $errors->has('body')])>{{ old('body') }}</textarea>
            </x-field>
            <button class="btn btn-block" type="submit"><x-icon name="send"/> Send Message</button>
        </form>
    </div>
@endsection
