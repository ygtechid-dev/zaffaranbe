<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'city',
        'province',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'operating_days',
        'is_active',
    ];

    protected $casts = [
        'operating_days' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'double',
        'longitude' => 'double',
    ];

    public function therapists()
    {
        return $this->hasMany(Therapist::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function cashierShifts()
    {
        return $this->hasMany(CashierShift::class);
    }

    public function paymentConfig()
    {
        return $this->hasOne(BranchPaymentConfig::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'branch_service');
    }

    public function facilities()
    {
        return $this->hasMany(Facility::class);
    }
}
