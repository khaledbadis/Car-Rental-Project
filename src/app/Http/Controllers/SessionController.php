<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SessionController
{
    public function store(Request $r): mixed
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', strtolower($v['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('ui.throttled')]);
        }
        if (! Auth::attempt(['email' => strtolower($v['email']), 'password' => $v['password'], 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('ui.invalid_login')]);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $r): mixed
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
