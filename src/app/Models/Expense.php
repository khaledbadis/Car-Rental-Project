<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'incurred_on' => 'immutable_date'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_id');
    }
}
