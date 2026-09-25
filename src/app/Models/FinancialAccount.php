<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccount extends Model
{
    protected $guarded = ['id'];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }
}
