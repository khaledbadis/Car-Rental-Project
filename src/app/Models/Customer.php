<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['phone_normalized'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'birth_date' => 'date', 'identity_issue_date' => 'date', 'identity_expiry_date' => 'date', 'licence_issue_date' => 'date', 'licence_expiry_date' => 'date'];
    }

    public function driver()
    {
        return $this->belongsTo(self::class, 'driver_id');
    }

    public function notes()
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function documents()
    {
        return $this->hasMany(Document::class)->whereNull('removed_at');
    }
}
