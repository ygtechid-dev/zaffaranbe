<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySettings extends Model
{
    protected $table = 'company_settings';

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'logo',
        'business_type',
        'address',
        'phone',
        'email',
        'website',
        'city',
        'province',
        'latitude',
        'longitude',
        'timezone',
        'time_format',
        'country',
        'currency',
        'facilities',
        'operating_days',
        'use_specific_operating_hours',
        'default_open_time',
        'default_close_time',
        'tax_percentage',
        'service_charge_percentage',
        'time_interval',
        'pos_time_interval',
        'payment_timeout',
        'min_dp',
        'min_dp_type',
        'commission_before_discount',
        'commission_after_discount',
        'commission_include_tax',
        'assistant_commission',
        'allow_unpaid_voucher_exchange',
        'voucher_expiration',
        'register_enabled',
        'rounding_enabled',
        'rounding_mode',
        'rounding_amount',
        'is_tax_enabled',
        'is_service_charge_enabled',
    ];

    protected $casts = [
        'facilities' => 'array',
        'operating_days' => 'array',
        'branch_id' => 'integer',
        'tax_percentage' => 'double',
        'service_charge_percentage' => 'double',
        'time_interval' => 'integer',
        'pos_time_interval' => 'integer',
        'payment_timeout' => 'integer',
        'min_dp' => 'double',
        'commission_before_discount' => 'boolean',
        'commission_after_discount' => 'boolean',
        'commission_include_tax' => 'boolean',
        'allow_unpaid_voucher_exchange' => 'boolean',
        'register_enabled' => 'boolean',
        'use_specific_operating_hours' => 'boolean',
        'rounding_enabled' => 'boolean',
        'rounding_amount' => 'integer',
        'is_tax_enabled' => 'boolean',
        'is_service_charge_enabled' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
