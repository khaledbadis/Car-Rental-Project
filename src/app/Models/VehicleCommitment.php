<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCommitment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime', 'emergency' => 'boolean'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
