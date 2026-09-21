<?php

use App\Http\Controllers\Api\FoundationController as Api;
use App\Http\Controllers\CatalogController;
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

Route::prefix('v1')->middleware(['auth:sanctum', 'account', 'throttle:api'])->group(function () {
    $controller = CatalogController::class;
    Route::get('customers/duplicates', [$controller, 'duplicates']);
    Route::get('categories', [$controller, 'categories']);
    Route::post('categories', [$controller, 'category']);
    Route::get('alerts', [$controller, 'alerts']);
    foreach (['vehicle', 'customer'] as $kind) {
        Route::get($kind.'s', [$controller, 'index'])->defaults('kind', $kind);
        Route::post($kind.'s', [$controller, 'create'])->defaults('kind', $kind);
        Route::get($kind.'s/{id}', [$controller, 'show'])->whereNumber('id')->defaults('kind', $kind);
        Route::put($kind.'s/{id}', [$controller, 'update'])->whereNumber('id')->defaults('kind', $kind);
        Route::post($kind.'s/{id}/archive', [$controller, 'archive'])->whereNumber('id')->defaults('kind', $kind);
        Route::post($kind.'s/{id}/documents', [$controller, 'upload'])->whereNumber('id')->defaults('kind', $kind);
    }
    Route::post('customers/{id}/notes', [$controller, 'note'])->whereNumber('id');
    Route::get('documents/{id}', [$controller, 'download'])->whereNumber('id');
    Route::delete('documents/{id}', [$controller, 'remove'])->whereNumber('id');
});
