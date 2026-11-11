<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Role;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Every ability in the role matrix becomes a gate (FR08).
        foreach (array_keys(Role::ABILITIES) as $ability) {
            Gate::define($ability, fn (User $user) => $user->is_active && Role::allows($user->role, $ability));
        }

        Paginator::defaultView('partials.pagination');
    }
}
