<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = ['name', 'percentage', 'locations'];

    protected $casts = [
        'locations' => 'array',
        'percentage' => 'double',
    ];
}
