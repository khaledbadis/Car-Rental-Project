<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is allowed only locally or in tests.');
        }
        foreach (['manager', 'agent', 'finance'] as $role) {
            User::firstOrCreate(['email' => $role.'@demo.test'], ['name' => ucfirst($role).' Demo', 'password' => 'LocalDemo!2026', 'role' => $role, 'locale' => 'fr', 'theme' => 'system']);
        }
    }
}
