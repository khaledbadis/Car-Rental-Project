<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
