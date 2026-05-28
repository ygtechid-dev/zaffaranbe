<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoService extends Model
{
    protected $fillable = [
        'promo_id',
        'service_id',
        'variant_id',
        'service_category_id',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    protected $appends = ['name', 'variant_name'];

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function variant()
    {
        return $this->belongsTo(ServiceVariant::class, 'variant_id');
    }

    public function getNameAttribute(): ?string
    {
        return $this->service?->name;
    }

    public function getVariantNameAttribute(): ?string
    {
        return $this->variant?->name;
    }
}