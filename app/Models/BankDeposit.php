<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankDeposit extends Model
{
    protected $fillable = [
        'branch_id',
        'created_by',
        'amount',
        'cash_before',
        'cash_after',
        'bank_name',
        'account_number',
        'deposit_proof',
        'notes',
        'deposit_date'
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'amount' => 'decimal:2',
        'cash_before' => 'decimal:2',
        'cash_after' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
