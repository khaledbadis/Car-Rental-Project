<?php

namespace App\Providers;

use App\Http\Middleware\AccountAccess;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach (array_unique(array_merge(...array_values(config('access.roles')))) as $permission) {
            Gate::define($permission, fn (User $user) => ! $user->must_change_password && $user->permits($permission));
        }
        Livewire::addPersistentMiddleware([AccountAccess::class]);
        RateLimiter::for('api', fn ($r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
    }
}
