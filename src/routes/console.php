<?php

use App\Models\User;
use App\Modules\Foundation\Actions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

Artisan::command('app:create-manager', function () {
    $name = $this->ask('Full name');
    $email = strtolower($this->ask('Email'));
    $password = $this->secret('Password (at least 12 characters)');
    $data = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:120', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:12|max:128'])->validate();
    DB::transaction(function () use ($data) {
        DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
        abort_if(User::where('role', 'manager')->where('active', true)->exists(), 409, 'An active manager already exists; use staff administration.');
        $u = User::create($data + ['role' => 'manager', 'must_change_password' => true]);
        app(Actions::class)->audit(null, 'manager.provisioned', 'user', $u->id, null, $u->only(['name', 'email', 'role']));
    });
    $this->info('Manager created. Password change is required on first sign-in.');
})->purpose('Securely provision the first manager; never accepts passwords in shell arguments');
