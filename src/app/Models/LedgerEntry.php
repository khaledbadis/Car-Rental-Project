<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
