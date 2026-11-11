@extends('layouts.app')
@php($editing = $activity->exists)
@section('title', $editing ? 'Edit Activity' : 'Schedule Activity')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('programs.index') }}">Programs</a> / {{ $editing ? $activity->title : 'New activity' }}</div>
            <h1>{{ $editing ? 'Edit Activity' : 'Schedule Activity' }}</h1>
            <p>Activities are the time slots volunteers sign up for and where distributions are logged.</p>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ $editing ? route('activities.update', $activity) : route('activities.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="program_id" label="Program" required>
                <select id="program_id" name="program_id" required @class(['is-invalid' => $errors->has('program_id')])>
                    <option value="">Select program…</option>
                    @foreach ($programs as $p)
                        <option value="{{ $p->id }}" @selected(old('program_id', $activity->program_id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="title" label="Activity title" required>
                <input id="title" type="text" name="title" value="{{ old('title', $activity->title) }}" maxlength="150" required placeholder="e.g. Brgy. San Jose Food Pack Distribution"
                       @class(['is-invalid' => $errors->has('title')])>
            </x-field>
            <x-field name="activity_date" label="Date" required>
                <input id="activity_date" type="date" name="activity_date" value="{{ old('activity_date', $activity->activity_date?->format('Y-m-d')) }}" required
                       @class(['is-invalid' => $errors->has('activity_date')])>
            </x-field>
            <x-field name="location" label="Location">
                <input id="location" type="text" name="location" value="{{ old('location', $activity->location) }}" maxlength="200" placeholder="e.g. Brgy. Hall, San Jose">
            </x-field>
            <x-field name="start_time" label="Start time" required>
                <input id="start_time" type="time" name="start_time" value="{{ old('start_time', substr((string) $activity->start_time, 0, 5)) }}" required
                       @class(['is-invalid' => $errors->has('start_time')])>
            </x-field>
            <x-field name="end_time" label="End time" required>
                <input id="end_time" type="time" name="end_time" value="{{ old('end_time', substr((string) $activity->end_time, 0, 5)) }}" required
                       @class(['is-invalid' => $errors->has('end_time')])>
            </x-field>
            <x-field name="volunteer_slots" label="Volunteers needed" required hint="Use 0 for no limit.">
                <input id="volunteer_slots" type="number" name="volunteer_slots" min="0" max="500" value="{{ old('volunteer_slots', $activity->volunteer_slots) }}" required>
            </x-field>
            <x-field name="status" label="Status" required>
                <select id="status" name="status">
                    @foreach (\App\Models\Activity::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', $activity->status) === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="description" label="Instructions for volunteers" class="span-2">
                <textarea id="description" name="description" maxlength="2000" placeholder="What to bring, where to meet, dress code…">{{ old('description', $activity->description) }}</textarea>
            </x-field>
        </div>
        <div class="form-foot">
            <a class="btn btn-outline" href="{{ $editing ? route('activities.show', $activity) : route('programs.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Activity</button>
        </div>
    </form>
@endsection
