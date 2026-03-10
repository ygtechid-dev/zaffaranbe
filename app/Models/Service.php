<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'gender', // 'all', 'male', 'female'
        'duration',
        'price',
        'special_price',
        'commission',
        'description',
        'image',
        'is_active',
        'is_global',
        'is_booking_online_enabled',
        'requires_room',
        'is_limited_availability',
        'availability_type',
        'availability_data',
        'all_branches_same_price',
        'branch_prices',
        'position',
        'service_category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'commission' => 'decimal:2',
        'duration' => 'integer',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'is_booking_online_enabled' => 'boolean',
        'requires_room' => 'boolean',
        'is_limited_availability' => 'boolean',
        'availability_data' => 'json',
        'all_branches_same_price' => 'boolean',
        'branch_prices' => 'json',
    ];

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_service');
    }

    public function variants()
    {
        return $this->hasMany(ServiceVariant::class);
    }

    public function priceLogs()
    {
        return $this->hasMany(ServicePriceLog::class);
    }

    public function getImageAttribute($value)
    {
        if (!$value)
            return null;
        if (str_starts_with($value, 'http'))
            return $value;
        return url($value);
    }
}
