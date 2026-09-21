<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccountAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if ($user && ! $user->active) {
            abort(403, __('ui.inactive'));
        }
        if ($user) {
            app()->setLocale($user->locale);
        } else {
            app()->setLocale(in_array(session('locale'), ['fr', 'ar', 'en']) ? session('locale') : 'fr');
        }
        if ($user?->must_change_password && ! $request->routeIs('preferences', 'password.update', 'logout', 'api.me', 'api.password', 'api.logout') && ! $request->is('livewire-*/*')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => __('ui.change_required'), 'code' => 'password_change_required'], 403);
            }

            return redirect()->route('preferences');
        }

        return $next($request);
    }
}
