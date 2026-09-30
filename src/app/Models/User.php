<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'locale', 'theme', 'must_change_password', 'phone', 'address'];

    protected $appends = ['has_avatar'];

    public function getHasAvatarAttribute(): bool
    {
        return (bool) $this->avatar_path;
    }

    protected $hidden = ['password', 'remember_token', 'avatar_path'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'must_change_password' => 'boolean', 'email_verified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (self $user) {
            $user->username = DB::table('users')->where('id', $user->id)->value('username');
        });
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
