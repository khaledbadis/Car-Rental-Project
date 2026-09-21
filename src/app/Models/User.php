<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'locale', 'theme', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'must_change_password' => 'boolean', 'email_verified_at' => 'datetime'];
    }

    public function permissions(): array
    {
        return config('access.roles.'.$this->role, []);
    }

    public function permits(string $permission): bool
    {
        return $this->active && in_array($permission, $this->permissions(), true)
            && (! $this->currentAccessToken() || $this->tokenCan($permission));
    }
}
