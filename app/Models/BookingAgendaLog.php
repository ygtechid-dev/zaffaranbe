<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingAgendaLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_item_id',
        'action',
        'old_data',
        'new_data',
        'price_difference',
        'changed_by',
        'notes'
    ];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
        'price_difference' => 'decimal:2'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
