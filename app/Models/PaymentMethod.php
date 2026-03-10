<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'icon',
        'description',
        'is_active',
        'sort_order',
        'fee',
        'account_number',
        'account_name',
        'is_global',
        'is_online',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'payment_method_branch');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'sort_order' => 'integer',
        'fee' => 'decimal:2',
    ];
}
