<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'is_global',
        'name',
        'code',
        'type',
        'capacity',
        'quantity',
        'extra_charge',
        'facilities',
        'description',
        'status',
        'is_active',
    ];

    protected $casts = [
        'extra_charge' => 'decimal:2',
        'capacity' => 'integer',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'room_branch');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
