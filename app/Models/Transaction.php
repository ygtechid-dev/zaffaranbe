<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_ref',
        'booking_id',
        'branch_id',
        'cashier_id',
        'type',
        'subtotal',
        'discount',
        'service_charge',
        'tax',
        'total',
        'payment_method',
        'cash_received',
        'change_amount',
        'notes',
        'transaction_date',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transaction_ref)) {
                $transaction->transaction_ref = 'TRX-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        });

        static::created(function ($transaction) {
            if ($transaction->status === 'completed') {
                app(\App\Services\LoyaltyService::class)->processTransaction($transaction);
            }
        });

        static::updated(function ($transaction) {
            if ($transaction->isDirty('status') && $transaction->status === 'completed') {
                app(\App\Services\LoyaltyService::class)->processTransaction($transaction);
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function pointRedemptions()
    {
        return $this->hasMany(PointRedemption::class);
    }
}
