<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'service_id',
        'product_id',
        'product_variant_id',
        'type',
        'commission_rate',
        'commission_type',
        'is_default',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
