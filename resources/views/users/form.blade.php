@extends('layouts.app')
@php($editing = $user->exists)
@section('title', $editing ? 'Edit Account' : 'New Account')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('users.index') }}">Users & Roles</a> / {{ $editing ? $user->name : 'New' }}</div>
            <h1>{{ $editing ? 'Edit Account' : 'New Account' }}</h1>
        </div>
    </div>

    <form class="card card-body" method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" id="user-form" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <x-field name="name" label="Full name" required>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="120" required @class(['is-invalid' => $errors->has('name')])>
            </x-field>
            <x-field name="email" label="Email (used to sign in)" required>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150" required autocomplete="off" @class(['is-invalid' => $errors->has('email')])>
            </x-field>
            <x-field name="role" label="Role" required>
                <select id="role" name="role" required @class(['is-invalid' => $errors->has('role')])>
                    @foreach (\App\Support\Role::LABELS as $key => $label)
                        @if ($key !== 'admin' || auth()->user()->hasRole('admin'))
                            <option value="{{ $key }}" @selected(old('role', $user->role) === $key)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </x-field>
            <x-field name="phone" label="Mobile number">
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30">
            </x-field>
            <x-field name="donor_id" label="Linked donor record" required class="span-2" data-role-only="donor" hint="Donor accounts only see this donor's contributions.">
                <select id="donor_id" name="donor_id" @class(['is-invalid' => $errors->has('donor_id')])>
                    <option value="">Select donor…</option>
                    @foreach ($donors as $d)
                        <option value="{{ $d->id }}" @selected(old('donor_id', $user->donor?->id) == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="volunteer_id" label="Linked volunteer profile" class="span-2" data-role-only="volunteer" hint="Leave blank to create a new volunteer profile from this account's details.">
                <select id="volunteer_id" name="volunteer_id">
                    <option value="">Create new profile</option>
                    @foreach ($volunteers as $v)
                        <option value="{{ $v->id }}" @selected(old('volunteer_id', $user->volunteer?->id) == $v->id)>{{ $v->fullName() }}{{ $v->email ? ' ('.$v->email.')' : '' }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="password" :label="$editing ? 'New password' : 'Password'" :required="! $editing" :hint="$editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.'">
                <input id="password" type="password" name="password" autocomplete="new-password" @class(['is-invalid' => $errors->has('password')])>
            </x-field>
            <x-field name="password_confirmation" label="Confirm password" :required="! $editing">
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
            </x-field>
            <div class="field span-2">
                <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Account is active (can sign in)</label>
            </div>
        </div>
        <div class="form-foot">
            <a class="btn btn-outline" href="{{ route('users.index') }}">Cancel</a>
            <button class="btn" type="submit">Save Account</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const role = document.getElementById('role');
        const sync = () => document.querySelectorAll('[data-role-only]').forEach((el) => { el.hidden = el.dataset.roleOnly !== role.value; });
        role.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
