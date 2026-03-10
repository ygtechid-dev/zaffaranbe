<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'code',
        'brand_name',
        'category',
        'description',
        'cost_price',
        'retail_price',
        'special_price',
        'reorder_point',
        'reorder_amount',
        'supplier_id',
        'branch_id',
        'is_global',
        'is_active',
        'is_booking_online_enabled',
        'use_location_prices',
        'location_prices',
        'image',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'product_branch');
    }

    protected $casts = [
        'cost_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'reorder_point' => 'integer',
        'reorder_amount' => 'integer',
        'is_active' => 'boolean',
        'is_booking_online_enabled' => 'boolean',
        'use_location_prices' => 'boolean',
        'location_prices' => 'array',
    ];

    protected $appends = [
        'computed_retail_price',
        'total_stock',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Check if product has variants
     */
    public function hasVariants()
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->count() > 0;
        }
        return $this->variants()->exists();
    }

    /**
     * Get computed retail price - uses first variant price if has variants, otherwise own price
     */
    /**
     * Get computed retail price - uses first variant price if has variants, otherwise own price
     */
    public function getComputedRetailPriceAttribute($value)
    {
        // If a specific value was set (e.g. via setAttribute in Controller for branch pricing), use it
        if ($value !== null) {
            return (float) $value;
        }

        if ($this->hasVariants()) {
            $firstVariant = $this->variants->first();
            return $firstVariant ? $firstVariant->retail_price : 0;
        }
        return (float) $this->retail_price;
    }

    /**
     * Get total stock - sum of all variant stocks if has variants, otherwise own stocks
     */
    public function getTotalStockAttribute()
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->sum(function ($variant) {
                // Ensure stocks are loaded for the variant
                return $variant->stocks->sum('quantity');
            });
        }
        return (int) $this->stocks->sum('quantity');
    }

    public function getImageAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return url($value);
    }
}
