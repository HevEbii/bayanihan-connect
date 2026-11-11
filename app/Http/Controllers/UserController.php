<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\Audit;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Administration module: user accounts and role management. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q')->trim().'%')
                ->orWhere('email', 'like', '%'.$request->string('q')->trim().'%')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', $this->formData(new User(['role' => Role::COORDINATOR, 'is_active' => true])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'role' => $data['role'], 'password' => $data['password'], 'is_active' => $data['is_active'],
            ]);
            $this->linkProfile($user, $data);

            return $user;
        });

        Audit::record('user.created', "Created {$user->roleLabel()} account for {$user->name}", $user);

        return redirect()->route('users.index')->with('status', "Account created for {$user->name}.");
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== $user->role || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot change your own role or deactivate your own account.']);
        }
        if ($data['role'] === Role::ADMIN && ! $request->user()->hasRole(Role::ADMIN)) {
            return back()->withErrors(['role' => 'Only an administrator can grant the administrator role.']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->only(['name', 'email', 'phone', 'role', 'is_active'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $this->linkProfile($user, $data);
        });

        Audit::record('user.updated', "Updated account for {$user->name} ({$user->roleLabel()})", $user);

        return redirect()->route('users.index')->with('status', "Account for {$user->name} updated.");
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'donors' => Donor::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->orderBy('name')->get(),
            'volunteers' => Volunteer::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->orderBy('last_name')->get(),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(Role::all())],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'donor_id' => ['nullable', 'required_if:role,donor', 'exists:donors,id'],
            'volunteer_id' => ['nullable', 'exists:volunteers,id'],
        ], ['donor_id.required_if' => 'Select the donor record this portal account belongs to.']);
        $data['is_active'] = $request->boolean('is_active');

        if ($data['role'] === Role::ADMIN && ! $request->user()->hasRole(Role::ADMIN)) {
            abort(403, 'Only an administrator can grant the administrator role.');
        }

        return $data;
    }

    /** Connects donor-portal and volunteer accounts to their registry records. */
    private function linkProfile(User $user, array $data): void
    {
        Donor::where('user_id', $user->id)->when($data['role'] === Role::DONOR, fn ($q) => $q->where('id', '!=', $data['donor_id']))
            ->update(['user_id' => null]);
        if ($data['role'] === Role::DONOR) {
            Donor::whereKey($data['donor_id'])->update(['user_id' => $user->id]);
        }

        if ($data['role'] !== Role::VOLUNTEER) {
            return;
        }
        if (! empty($data['volunteer_id'])) {
            Volunteer::where('user_id', $user->id)->where('id', '!=', $data['volunteer_id'])->update(['user_id' => null]);
            Volunteer::whereKey($data['volunteer_id'])->update(['user_id' => $user->id]);
        } elseif (! $user->volunteer()->exists()) {
            $parts = explode(' ', $user->name, 2);
            Volunteer::create(['user_id' => $user->id, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
                'email' => $user->email, 'phone' => $user->phone, 'status' => 'active']);
        }
    }
}
