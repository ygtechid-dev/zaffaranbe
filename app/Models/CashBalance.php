<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBalance extends Model
{
    protected $fillable = [
        'branch_id',
        'current_balance',
        'last_updated'
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
