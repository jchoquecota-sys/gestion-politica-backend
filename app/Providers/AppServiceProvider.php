<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admin bypasses every Gate/permission check automatically.
        // Returning true here short-circuits all subsequent ability checks.
        // Returning null lets the normal checks continue for other roles.
        Gate::before(function ($user, string $ability): ?bool {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
