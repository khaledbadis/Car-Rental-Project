<?php

use App\Http\Controllers\Api\FoundationController as Api;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'account', 'throttle:api'])->group(function () {
    Route::get('me', [Api::class, 'me'])->name('api.me');
    Route::patch('me/preferences', [Api::class, 'preferences']);
    Route::put('me/password', [Api::class, 'password'])->name('api.password');
    Route::delete('token', [Api::class, 'logout'])->name('api.logout');
    Route::get('staff', [Api::class, 'staff']);
    Route::post('staff', [Api::class, 'create']);
    Route::patch('staff/{id}', [Api::class, 'update'])->whereNumber('id');
    Route::get('settings', [Api::class, 'settings']);
    Route::put('settings', [Api::class, 'saveSettings']);
    Route::get('audit-events', [Api::class, 'audit']);
});
