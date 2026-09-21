<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected $appends = ['expiry_status'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'removed_at' => 'datetime'];
    }

    public function getExpiryStatusAttribute(): string
    {
        if (! $this->expires_at) {
            return 'no_expiry';
        }
        $days = CarbonImmutable::today('Africa/Algiers')->diffInDays(CarbonImmutable::parse($this->expires_at->format('Y-m-d'), 'Africa/Algiers'), false);

        return $days < 0 ? 'expired' : ($days <= 7 ? 'due_7' : ($days <= 15 ? 'due_15' : ($days <= 30 ? 'due_30' : 'valid')));
    }
}
