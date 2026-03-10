<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePriceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'service_variant_id',
        'old_price',
        'new_price',
        'price_type',
        'changed_by',
        'notes'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function variant()
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
