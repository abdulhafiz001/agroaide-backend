<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('administer', fn ($user) => $user->isAdmin());

        foreach (config('security.rate_limits') as $name => [$attempts, $minutes]) {
            RateLimiter::for($name, function (Request $request) use ($attempts, $minutes, $name) {
                $identity = $request->user()?->id
                    ?? strtolower((string) ($request->input('identifier') ?: $request->input('email') ?: $request->ip()));

                return Limit::perMinutes((int) $minutes, (int) $attempts)->by($name.'|'.$identity.'|'.$request->ip());
            });
        }
    }
}
