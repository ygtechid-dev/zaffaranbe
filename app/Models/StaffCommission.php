<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'branch_id',
        'transaction_id',
        'booking_id',
        'item_id',
        'item_type',
        'item_name',
        'item_variant_name',
        'sales_amount',
        'qty',
        'commission_percentage',
        'commission_amount',
        'payment_date',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'sales_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Therapist::class, 'staff_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
