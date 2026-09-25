<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionPhoto extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }
}
