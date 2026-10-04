<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['serviced_on' => 'immutable_date', 'next_due_on' => 'immutable_date', 'cost_cents' => 'integer', 'mileage_km' => 'integer', 'next_due_km' => 'integer'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function block()
    {
        return $this->belongsTo(VehicleCommitment::class, 'vehicle_commitment_id');
    }

    public function attachments()
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }
}
