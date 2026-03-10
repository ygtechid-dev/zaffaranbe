<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'salon_name',
        'pic_name',
        'phone',
        'email',
        'city',
        'status',
        'notes',
        'source',
    ];

    /**
     * Status pipeline constants
     */
    const STATUS_NEW = 'new';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_DEMO_SCHEDULED = 'demo_scheduled';
    const STATUS_NEGOTIATION = 'negotiation';
    const STATUS_CONVERTED = 'converted';
    const STATUS_LOST = 'lost';

    /**
     * Get all valid statuses
     */
    public static function validStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_CONTACTED,
            self::STATUS_DEMO_SCHEDULED,
            self::STATUS_NEGOTIATION,
            self::STATUS_CONVERTED,
            self::STATUS_LOST,
        ];
    }

    /**
     * Scope: filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: search by name, phone, email, salon
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('salon_name', 'like', "%{$search}%")
              ->orWhere('pic_name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
