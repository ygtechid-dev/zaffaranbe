<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchPaymentConfig extends Model
{
    protected $fillable = [
        'branch_id',
        'payment_gateway',
        'midtrans_server_key',
        'midtrans_client_key',
        'midtrans_merchant_id',
        'midtrans_is_production',
        'xendit_api_key',
        'xendit_callback_token',
        'xendit_is_production',
        'enabled_payment_methods',
        'bank_accounts',
        'ewallet_accounts',
        'minimum_payment',
        'down_payment_percentage',
        'down_payment_amount',
        'allow_installment',
        'max_installment_months',
        'auto_confirm_payment',
        'payment_confirmation_timeout',
        'is_active'
    ];

    protected $casts = [
        'enabled_payment_methods' => 'array',
        'bank_accounts' => 'array',
        'ewallet_accounts' => 'array',
        'minimum_payment' => 'decimal:2',
        'down_payment_percentage' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'midtrans_is_production' => 'boolean',
        'xendit_is_production' => 'boolean',
        'allow_installment' => 'boolean',
        'auto_confirm_payment' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'midtrans_server_key',
        'midtrans_client_key',
        'xendit_api_key',
        'xendit_callback_token',
    ];

    /**
     * Get the branch that owns the payment config
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get masked sensitive data for display
     */
    public function getMaskedServerKeyAttribute()
    {
        if (!$this->midtrans_server_key)
            return null;
        return substr($this->midtrans_server_key, 0, 8) . '***' . substr($this->midtrans_server_key, -4);
    }

    public function getMaskedClientKeyAttribute()
    {
        if (!$this->midtrans_client_key)
            return null;
        return substr($this->midtrans_client_key, 0, 8) . '***' . substr($this->midtrans_client_key, -4);
    }

    public function getMaskedXenditKeyAttribute()
    {
        if (!$this->xendit_api_key)
            return null;
        return substr($this->xendit_api_key, 0, 8) . '***' . substr($this->xendit_api_key, -4);
    }

    /**
     * Append masked keys to array/json output
     */
    protected $appends = ['masked_server_key', 'masked_client_key', 'masked_xendit_key'];
}
