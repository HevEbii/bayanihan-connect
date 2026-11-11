@extends('layouts.app')
@php($editing = $beneficiary->exists)
@section('title', $editing ? 'Edit Beneficiary' : 'Register Beneficiary')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('beneficiaries.index') }}">Beneficiaries</a> / {{ $editing ? $beneficiary->code : 'New' }}</div>
            <h1>{{ $editing ? 'Update Beneficiary Record' : 'Register Beneficiary' }}</h1>
            <p>{{ $editing ? 'Changes are shared instantly with every coordinator.' : 'Works offline: if you lose signal, the entry is saved on this device and synced later.' }}</p>
        </div>
    </div>

    <form class="card card-body" method="POST"
          action="{{ $editing ? route('beneficiaries.update', $beneficiary) : route('beneficiaries.store') }}"
          @unless ($editing) data-offline="New beneficiary" data-offline-label="#last_name" @endunless novalidate>
        @csrf
        @if ($editing) @method('PUT') @else <input type="hidden" name="client_uuid" data-client-uuid value="{{ old('client_uuid') }}"> @endif

        <div class="form-grid">
            <x-field name="first_name" label="First name" required>
                <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $beneficiary->first_name) }}" required maxlength="100"
                       placeholder="e.g. Juan" @class(['is-invalid' => $errors->has('first_name')])>
            </x-field>
            <x-field name="last_name" label="Last name" required>
                <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $beneficiary->last_name) }}" required maxlength="100"
                       placeholder="e.g. Dela Cruz" @class(['is-invalid' => $errors->has('last_name')])>
            </x-field>
            <x-field name="category" label="Category" required>
                <select id="category" name="category" required>
                    @foreach (\App\Models\Beneficiary::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $beneficiary->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="program_id" label="Program enrolled in">
                <select id="program_id" name="program_id">
                    <option value="">Not yet enrolled</option>
                    @foreach ($programs as $p)
                        <option value="{{ $p->id }}" @selected(old('program_id', $beneficiary->program_id) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="barangay" label="Barangay" required>
                <input id="barangay" type="text" name="barangay" value="{{ old('barangay', $beneficiary->barangay) }}" required maxlength="120"
                       placeholder="e.g. Brgy. San Jose" @class(['is-invalid' => $errors->has('barangay')])>
            </x-field>
            <x-field name="household_size" label="Household size" required>
                <input id="household_size" type="number" name="household_size" min="1" max="50" value="{{ old('household_size', $beneficiary->household_size) }}" required
                       @class(['is-invalid' => $errors->has('household_size')])>
            </x-field>
            <x-field name="address" label="Street / sitio" class="span-2">
                <input id="address" type="text" name="address" value="{{ old('address', $beneficiary->address) }}" maxlength="255" placeholder="Optional">
            </x-field>
            <x-field name="birth_date" label="Birth date">
                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $beneficiary->birth_date?->format('Y-m-d')) }}" max="{{ today()->subDay()->format('Y-m-d') }}"
                       @class(['is-invalid' => $errors->has('birth_date')])>
            </x-field>
            <x-field name="sex" label="Sex">
                <select id="sex" name="sex">
                    <option value="">Prefer not to say</option>
                    <option value="female" @selected(old('sex', $beneficiary->sex) === 'female')>Female</option>
                    <option value="male" @selected(old('sex', $beneficiary->sex) === 'male')>Male</option>
                </select>
            </x-field>
            <x-field name="contact_number" label="Contact number">
                <input id="contact_number" type="tel" name="contact_number" value="{{ old('contact_number', $beneficiary->contact_number) }}" maxlength="30" placeholder="e.g. 0917 123 4567">
            </x-field>
            <x-field name="notes" label="Notes" class="span-2" hint="Visible only to coordinators, the Director, and administrators.">
                <textarea id="notes" name="notes" maxlength="2000" placeholder="Special needs, school level, etc.">{{ old('notes', $beneficiary->notes) }}</textarea>
            </x-field>
        </div>

        <div class="form-foot">
            <a class="btn btn-outline" href="{{ $editing ? route('beneficiaries.show', $beneficiary) : route('beneficiaries.index') }}">Cancel</a>
            <button class="btn" type="submit">{{ $editing ? 'Save Changes' : 'Save Beneficiary' }}</button>
        </div>
    </form>
@endsection
