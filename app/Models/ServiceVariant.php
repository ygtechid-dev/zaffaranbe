<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'duration',
        'price',
        'special_price',
        'capital_price',
        'is_active',
        'branch_ids',
        'all_branches_same_price',
        'branch_prices',
        'is_limited_availability',
        'availability_type',
        'availability_data',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'capital_price' => 'decimal:2',
        'is_active' => 'boolean',
        'branch_ids' => 'json',
        'all_branches_same_price' => 'boolean',
        'branch_prices' => 'json',
        'is_limited_availability' => 'boolean',
        'availability_type' => 'string',
        'availability_data' => 'json',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function priceLogs()
    {
        return $this->hasMany(ServicePriceLog::class, 'service_variant_id');
    }
}
