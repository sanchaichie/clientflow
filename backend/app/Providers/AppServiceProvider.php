<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
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
        // Administrators may do everything (spec §3). Returning null (not
        // false) for other users lets the normal policy method decide.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        // Reset links open the Nuxt page, not a Laravel route.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        // Fail loudly in development when a relationship is lazy-loaded inside
        // a loop (N+1 queries) instead of being eager-loaded with ->with().
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
