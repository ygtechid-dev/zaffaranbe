<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Promo extends Model
{
    protected $fillable = [
        'title',
        'type',
        'discount',
        'code',
        'quota',
        'used',
        'start_date',
        'end_date',
        'status',
        'description',
          'terms',   
        'min_purchase',
        'max_discount',
        'applicable_services',
        'branch_id',
        'service_category_id',
        'is_featured',
    ];

    protected $casts = [
        'discount'             => 'decimal:2',
        'min_purchase'         => 'decimal:2',
        'max_discount'         => 'decimal:2',
        'applicable_services'  => 'array',
          'terms'                => 'array', 
        'start_date'           => 'datetime',
        'end_date'             => 'datetime',
        'is_featured'          => 'boolean',
    ];

    protected $appends = ['remaining_quota', 'is_valid'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // NEW
    public function promoServices()
    {
        return $this->hasMany(PromoService::class);
    }

    public function getRemainingQuotaAttribute()
    {
        return $this->quota - $this->used;
    }

    public function getIsValidAttribute()
    {
        $now = Carbon::now();
        return $this->status === 'active'
            && $now->gte($this->start_date)
            && $now->lte($this->end_date)
            && $this->used < $this->quota;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now())
            ->whereRaw('used < quota');
    }

    public function incrementUsage()
    {
        $this->increment('used');

        if ($this->used >= $this->quota) {
            $this->update(['status' => 'expired']);
        }
    }
}