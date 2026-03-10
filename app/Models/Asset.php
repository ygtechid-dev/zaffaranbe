<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'name',
        'category',
        'location',
        'purchase_date',
        'purchase_price',
        'condition',
        'last_maintenance',
        'notes',
        'status',
        'branch_id',
        'is_global',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'asset_branch');
    }

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'last_maintenance' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
