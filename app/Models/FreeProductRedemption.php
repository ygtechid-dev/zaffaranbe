<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreeProductRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'product_id',
        'user_id',
        'booking_id',
        'branch_id',
        'invoice_no',
        'redeemed_at',
        'notes',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
