<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountType extends Model
{
    protected $table = 'discount_types';

    protected $fillable = ['name', 'type', 'value', 'applies_to'];

    protected $casts = [
        'applies_to' => 'array',
        'value' => 'double',
    ];
}
