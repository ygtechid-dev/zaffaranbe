<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'transaction_id',
        'points_used',
        'type',
        'item_name',
        'discount_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
