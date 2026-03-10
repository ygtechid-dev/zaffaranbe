<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AutomationRule extends Model
{
    protected $fillable = [
        'name',
        'trigger',
        'branch_id',
        'is_global',
        'is_active',
        'days_offset',
        'channel',
        'message',
        'discount_code',
        'last_triggered',
        'total_sent'
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'automation_rule_branch');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'last_triggered' => 'datetime'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementSent()
    {
        $this->increment('total_sent');
        $this->update(['last_triggered' => Carbon::now()]);
    }
}
