<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Therapist extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'gender',
        'specialization',
        'shift',
        'rating',
        'photo',
        'color',
        'social_media',
        'notes',
        'start_work_date',
        'end_work_date',
        'is_active',
        'default_service_commission',
        'default_product_commission',
        'service_commission_type',
        'product_commission_type',
        'commission_type',
        'is_booking_online_enabled',
    ];

    protected $hidden = [
        'rating',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_booking_online_enabled' => 'boolean',
        'rating' => 'float',
        'default_service_commission' => 'decimal:2',
        'default_product_commission' => 'decimal:2',
        'start_work_date' => 'date',
        'end_work_date' => 'date',
    ];

    public function getPhotoAttribute($value)
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return url($value);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schedules()
    {
        return $this->hasMany(TherapistSchedule::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function commissions()
    {
        return $this->hasMany(TherapistCommission::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'staff_id');
    }

    public function getCommissionForService($serviceId, $type = 'service')
    {
        $override = $this->commissions()
            ->where('service_id', $serviceId)
            ->where('type', $type)
            ->first();

        if ($override) {
            return [
                'rate' => $override->commission_rate,
                'type' => $override->commission_type,
            ];
        }

        // Return default
        if ($type === 'service') {
            return [
                'rate' => $this->default_service_commission ?? 0,
                'type' => $this->service_commission_type ?? $this->commission_type ?? 'percent',
            ];
        }

        return [
            'rate' => $this->default_product_commission ?? 0,
            'type' => $this->product_commission_type ?? $this->commission_type ?? 'percent',
        ];
    }
}
