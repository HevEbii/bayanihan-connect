@extends('layouts.app')
@php($editing = $volunteer->exists)
@section('title', $editing ? 'Edit Volunteer' : 'Register Volunteer')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('volunteers.index') }}">Volunteers</a> / {{ $editing ? $volunteer->fullName() : 'New' }}</div>
            <h1>{{ $editing ? 'Edit Volunteer' : 'Register Volunteer' }}</h1>
            <p>To let a volunteer sign in and receive messages, create a Volunteer account under Users &amp; Roles.</p>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ $editing ? route('volunteers.update', $volunteer) : route('volunteers.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="first_name" label="First name" required>
                <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $volunteer->first_name) }}" maxlength="100" required @class(['is-invalid' => $errors->has('first_name')])>
            </x-field>
            <x-field name="last_name" label="Last name" required>
                <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $volunteer->last_name) }}" maxlength="100" required @class(['is-invalid' => $errors->has('last_name')])>
            </x-field>
            <x-field name="phone" label="Mobile number">
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $volunteer->phone) }}" maxlength="30">
            </x-field>
            <x-field name="email" label="Email">
                <input id="email" type="email" name="email" value="{{ old('email', $volunteer->email) }}" maxlength="150" @class(['is-invalid' => $errors->has('email')])>
            </x-field>
            <x-field name="skills" label="Skills" hint="Comma separated, e.g. Driving, First aid, Teaching">
                <input id="skills" type="text" name="skills" value="{{ old('skills', $volunteer->skills) }}" maxlength="255">
            </x-field>
            <x-field name="availability" label="Availability">
                <input id="availability" type="text" name="availability" value="{{ old('availability', $volunteer->availability) }}" maxlength="255" placeholder="e.g. Weekends, Saturday mornings">
            </x-field>
            <x-field name="status" label="Status" required>
                <select id="status" name="status">
                    <option value="active" @selected(old('status', $volunteer->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $volunteer->status) === 'inactive')>Inactive</option>
                </select>
            </x-field>
        </div>
        <div class="form-foot">
            <a class="btn btn-outline" href="{{ $editing ? route('volunteers.show', $volunteer) : route('volunteers.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Volunteer</button>
        </div>
    </form>
@endsection
