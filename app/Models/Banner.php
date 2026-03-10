<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($banner) {
            if (!$banner->position) {
                $maxPosition = static::max('position');
                $banner->position = $maxPosition ? $maxPosition + 1 : 1;
            }
        });
    }

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'branch_id',
        'is_global',
        'link_url',
        'link_type',
        'category',
        'type',
        'position',
        'is_active',
        'start_date',
        'end_date',
        'views',
        'clicks',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'banner_branch');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'position' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', \Carbon\Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', \Carbon\Carbon::now());
            });
    }
}
