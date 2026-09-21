<?php

use App\Http\Controllers\SessionController;
use App\Livewire\Audit;
use App\Livewire\Preferences;
use App\Livewire\Settings;
use App\Livewire\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});
Route::post('/locale', function (Request $r) {
    $v = $r->validate(['locale' => 'required|in:fr,ar,en']);
    session($v);

    return back();
})->name('locale');
Route::middleware(['auth', 'account'])->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');
    Route::get('/preferences', Preferences::class)->name('preferences');
    Route::get('/staff', Staff::class)->middleware('can:staff.manage')->name('staff');
    Route::get('/settings', Settings::class)->middleware('can:settings.manage')->name('settings');
    Route::get('/audit', Audit::class)->middleware('can:audit.view')->name('audit');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
