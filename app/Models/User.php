<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Laravel\Lumen\Auth\Authorizable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'birth_date',
        'address',
        'password',
        'role',
        'otp',
        'otp_expires_at',
        'city_id',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
        'branch_id',
        'registration_source',
        'membership_status',
        'has_app_account',
        'notes',
        'is_verified',
        'is_active',
        'staff_id',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'otp_expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'has_app_account' => 'boolean',
        'staff_id' => 'integer',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'name' => $this->name,
        ];
    }

    // Relationships
    public function therapist()
    {
        return $this->belongsTo(Therapist::class, 'staff_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function completedBookings()
    {
        return $this->hasMany(Booking::class)->where('status', 'completed')->where('payment_status', 'paid');
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    public function cashierShifts()
    {
        return $this->hasMany(CashierShift::class, 'cashier_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'cashier_id');
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
