<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffTip extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'branch_id',
        'transaction_id',
        'amount_collected',
        'amount_returned',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_collected' => 'decimal:2',
        'amount_returned' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Therapist::class, 'staff_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
