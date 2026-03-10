<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'service_id',
        'service_variant_id',
        'therapist_id',
        'room_id',
        'price',
        'room_charge',
        'duration',
        'start_time',
        'end_time',
        'guest_name',
        'guest_phone',
        'guest_type',
        'guest_age',
        'status', 
        'refund_amount', 
        'cancellation_reason'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'room_charge' => 'decimal:2',
        'duration' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function variant()
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
