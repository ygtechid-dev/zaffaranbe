<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'account_number',
        'account_name',
        'card_last_four',
        'card_brand',
        'is_default',
        'is_verified',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
    ];

    protected $hidden = [
        'account_number', // Hide full account number
    ];

    protected $appends = ['masked_account'];

    public function getMaskedAccountAttribute()
    {
        if (!$this->account_number)
            return null;
        $len = strlen($this->account_number);
        if ($len <= 4)
            return $this->account_number;
        return str_repeat('*', $len - 4) . substr($this->account_number, -4);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function setDefault($userId, $paymentMethodId)
    {
        // Remove default from all others
        self::where('user_id', $userId)->update(['is_default' => false]);
        // Set new default
        self::where('id', $paymentMethodId)->where('user_id', $userId)->update(['is_default' => true]);
    }
}
