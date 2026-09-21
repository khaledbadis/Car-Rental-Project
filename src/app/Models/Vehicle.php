<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'entered_service_at' => 'date', 'daily_rate' => 'decimal:2', 'weekly_rate' => 'decimal:2', 'monthly_rate' => 'decimal:2'];
    }

    public function category()
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class)->whereNull('removed_at');
    }
}
