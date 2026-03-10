<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'branch_id',
        'location',
        'quantity',
        'average_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'average_cost' => 'decimal:2',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
