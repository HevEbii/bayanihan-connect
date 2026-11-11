@extends('layouts.app')
@section('title', 'Users & Roles')

@section('content')
    <div class="page-head">
        <div>
            <h1>Users & Roles</h1>
            <p>Each role only sees the modules it needs. Beneficiary and financial data are restricted by role.</p>
        </div>
        <a class="btn" href="{{ route('users.create') }}"><x-icon name="plus"/> New Account</a>
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name or email">
        </div>
        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                @foreach (\App\Support\Role::LABELS as $key => $label)
                    <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong></td>
                    <td>{{ $u->email }}</td>
                    <td><span class="tag">{{ $u->roleLabel() }}</span></td>
                    <td><span @class(['tag', 'tag-green' => $u->is_active, 'tag-red' => ! $u->is_active])>{{ $u->is_active ? 'Active' : 'Deactivated' }}</span></td>
                    <td class="num"><a class="btn btn-sm btn-outline" href="{{ route('users.edit', $u) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $users->links() }}
    </div>

    <div class="card" style="margin-top:20px">
        <div class="card-head"><h2>Role Permissions</h2><p>What each role can access</p></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Permission</th>@foreach (\App\Support\Role::LABELS as $label)<th class="num">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach (\App\Support\Role::ABILITIES as $ability => $roles)
                <tr>
                    <td class="mono">{{ $ability }}</td>
                    @foreach (array_keys(\App\Support\Role::LABELS) as $role)
                        <td class="num">{!! in_array($role, $roles, true) ? '<span class="tag tag-green">✓</span>' : '<span style="color:var(--border-strong)">—</span>' !!}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endsection
