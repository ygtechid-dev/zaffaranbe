<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'birth_date',
        'address',
        'city_id',
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
        'branch_id',
        'password',
        'otp',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'otp',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'otp_expires_at' => 'datetime',
    ];
}
