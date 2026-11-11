@extends('layouts.app')
@php($editing = $donor->exists)
@section('title', $editing ? 'Edit Donor' : 'Add Donor')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('donors.index') }}">Donor Registry</a> / {{ $editing ? $donor->name : 'New' }}</div>
            <h1>{{ $editing ? 'Edit Donor' : 'Add Donor' }}</h1>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ $editing ? route('donors.update', $donor) : route('donors.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        @if (request('return_to_donation')) <input type="hidden" name="return_to_donation" value="1"> @endif

        <div class="form-grid">
            <x-field name="name" label="Donor / organization name" required>
                <input id="name" type="text" name="name" value="{{ old('name', $donor->name) }}" required maxlength="150"
                       placeholder="e.g. GMA Kapuso Foundation" @class(['is-invalid' => $errors->has('name')])>
            </x-field>
            <x-field name="type" label="Donor type" required>
                <select id="type" name="type" required>
                    @foreach (\App\Models\Donor::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $donor->type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="contact_person" label="Contact person">
                <input id="contact_person" type="text" name="contact_person" value="{{ old('contact_person', $donor->contact_person) }}" maxlength="120">
            </x-field>
            <x-field name="email" label="Email">
                <input id="email" type="email" name="email" value="{{ old('email', $donor->email) }}" maxlength="150" @class(['is-invalid' => $errors->has('email')])>
            </x-field>
            <x-field name="phone" label="Phone">
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $donor->phone) }}" maxlength="30">
            </x-field>
            <x-field name="address" label="Address">
                <input id="address" type="text" name="address" value="{{ old('address', $donor->address) }}" maxlength="255">
            </x-field>
        </div>
        <div class="form-foot">
            <a class="btn btn-outline" href="{{ $editing ? route('donors.show', $donor) : route('donors.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Donor</button>
        </div>
    </form>
@endsection
