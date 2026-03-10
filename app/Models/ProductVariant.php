<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'retail_price',
        'special_price',
        'cost_price',
        'track_stock',
        'is_active',
        'use_location_prices',
        'location_prices',
        'image',
    ];

    protected $appends = [
        'total_stock',
        'computed_retail_price',
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'special_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'use_location_prices' => 'boolean',
        'location_prices' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductVariantStock::class);
    }

    /**
     * Get total stock across all branches
     */
    public function getTotalStockAttribute()
    {
        return $this->stocks->sum('quantity');
    }

    /**
     * Get computed retail price
     */
    public function getComputedRetailPriceAttribute($value)
    {
        if ($value !== null) {
            return (float) $value;
        }
        return (float) $this->retail_price;
    }

    public function getImageAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return url($value);
    }
}
