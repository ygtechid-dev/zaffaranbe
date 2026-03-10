<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $fillable = [
        'booking_data',
        'status',
        'expired_at',
        'transaction_id',
    ];

    protected $casts = [
        'booking_data' => 'array',
        'expired_at' => 'datetime',
    ];
}
