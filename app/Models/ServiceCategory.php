<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = ['name', 'description', 'color', 'image', 'is_global', 'branch_id', 'position'];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'service_category_branch');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'service_category_id');
    }

    public function getImageAttribute($value)
    {
        if (!$value)
            return null;
        if (str_starts_with($value, 'http'))
            return $value;
        return url($value);
    }
}
