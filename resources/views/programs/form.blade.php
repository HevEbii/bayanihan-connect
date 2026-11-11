@extends('layouts.app')
@php($editing = $program->exists)
@section('title', $editing ? 'Edit Program' : 'New Program')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('programs.index') }}">Programs</a> / {{ $editing ? $program->name : 'New' }}</div>
            <h1>{{ $editing ? 'Edit Program' : 'New Program' }}</h1>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ $editing ? route('programs.update', $program) : route('programs.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="name" label="Program name" required class="span-2">
                <input id="name" type="text" name="name" value="{{ old('name', $program->name) }}" maxlength="150" required placeholder="e.g. Noche Buena Relief Drive 2026"
                       @class(['is-invalid' => $errors->has('name')])>
            </x-field>
            <x-field name="category" label="Category" required>
                <select id="category" name="category" required>
                    @foreach (\App\Models\Program::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $program->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="coordinator_id" label="Project coordinator">
                <select id="coordinator_id" name="coordinator_id">
                    <option value="">Unassigned</option>
                    @foreach ($coordinators as $c)
                        <option value="{{ $c->id }}" @selected(old('coordinator_id', $program->coordinator_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="start_date" label="Start date">
                <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $program->start_date?->format('Y-m-d')) }}">
            </x-field>
            <x-field name="end_date" label="End date">
                <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $program->end_date?->format('Y-m-d')) }}" @class(['is-invalid' => $errors->has('end_date')])>
            </x-field>
            <x-field name="status" label="Status" required>
                <select id="status" name="status" required>
                    @foreach (\App\Models\Program::STATUSES as $s)
                        <option value="{{ $s }}" @selected(old('status', $program->status) === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="description" label="Description" class="span-2">
                <textarea id="description" name="description" maxlength="2000">{{ old('description', $program->description) }}</textarea>
            </x-field>
        </div>
        <div class="form-foot">
            <a class="btn btn-outline" href="{{ $editing ? route('programs.show', $program) : route('programs.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Program</button>
        </div>
    </form>
@endsection
