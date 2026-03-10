<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'total_quantity',
        'used_quantity',
        'start_date',
        'expiry_date',
        'status',
        'branch_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'total_quantity' => 'integer',
        'used_quantity' => 'integer',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function usages()
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function getRemainingAttribute()
    {
        return $this->total_quantity - $this->used_quantity;
    }

    public function getStatusLabelAttribute()
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return 'Expired';
        }
        if ($this->remaining <= 0) {
            return 'Sold Out';
        }
        return 'Active';
    }
}
