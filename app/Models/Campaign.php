<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'type',
        'target_audience',
        'branch_id',
        'is_global',
        'message',
        'image',
        'sent',
        'opened',
        'converted',
        'status',
        'scheduled_at',
        'start_date',
        'end_date',
        'sent_at'
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'campaign_branch');
    }

    protected $casts = [
        'scheduled_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'sent_at' => 'datetime'
    ];

    protected $appends = ['open_rate', 'conversion_rate', 'image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? url('storage/' . $this->image) : null;
    }

    public function getOpenRateAttribute()
    {
        if ($this->sent == 0)
            return 0;
        return round(($this->opened / $this->sent) * 100, 1);
    }

    public function getConversionRateAttribute()
    {
        if ($this->sent == 0)
            return 0;
        return round(($this->converted / $this->sent) * 100, 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
