<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'handed_over_at' => 'immutable_datetime', 'returned_at' => 'immutable_datetime', 'pricing' => 'array', 'snapshot' => 'array'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver()
    {
        return $this->belongsTo(Customer::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function account()
    {
        return $this->hasOne(FinancialAccount::class);
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class);
    }
}
