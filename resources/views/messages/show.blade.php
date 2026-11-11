@extends('layouts.app')
@section('title', 'Messages · '.$partner->name)

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('messages.index') }}">Messages</a> / {{ $partner->name }}</div>
            <h1>{{ $partner->name }}</h1>
            <p>{{ $partner->roleLabel() }}</p>
        </div>
    </div>

    <div class="card">
        <div class="thread" id="thread">
            @forelse ($thread as $m)
                <div @class(['bubble', 'mine' => (int) $m->sender_id === auth()->id()])>
                    @if ($m->activity)<span class="activity-ref">Re: {{ $m->activity->title }}</span>@endif
                    <div class="msg-body">{{ $m->body }}</div>
                    <small>{{ $m->created_at->format('M j, g:i A') }}@if ((int) $m->sender_id === auth()->id()) · {{ $m->read_at ? 'Seen' : 'Sent' }}@endif</small>
                </div>
            @empty
                <div class="empty">No messages yet. Say hello!</div>
            @endforelse
        </div>
        <form class="composer" method="POST" action="{{ route('messages.store') }}" data-offline="Message to {{ $partner->name }}" novalidate>
            @csrf
            <input type="hidden" name="client_uuid" data-client-uuid>
            <input type="hidden" name="recipient_id" value="{{ $partner->id }}">
            <textarea name="body" maxlength="5000" required placeholder="Write a message…" aria-label="Message" @class(['is-invalid' => $errors->has('body')])>{{ old('body') }}</textarea>
            @error('body')<div class="field-error">{{ $message }}</div>@enderror
            <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
                <select name="activity_id" aria-label="Link to activity" style="flex:1;min-width:200px">
                    <option value="">Link to an activity (optional)</option>
                    @foreach ($activities as $a)
                        <option value="{{ $a->id }}">{{ $a->label() }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit"><x-icon name="send"/> Send</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>document.getElementById('thread')?.scrollTo(0, 1e9);</script>
@endpush
