<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'branch_id',
        'plan_key',
        'interval',
        'status',
        'payment_method',
        'payment_ref',
        'amount',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_key', 'plan_key');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->expires_at === null) return true; // lifetime or starter
        return $this->expires_at->isFuture();
    }

    public function isPro(): bool
    {
        return $this->isActive() && $this->plan_key === 'pro';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopePro($query)
    {
        return $query->where('plan_key', 'pro');
    }
}
