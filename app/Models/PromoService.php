<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoService extends Model
{
    protected $fillable = [
        'promo_id',
        'service_id',
        'service_category_id',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
    ];

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}