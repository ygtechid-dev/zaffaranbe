<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_ref',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_type',
        'guest_age',
        'branch_id',
        'service_id',
        'therapist_id',
        'room_id',
        'booking_date',
        'start_time',
        'end_time',
        'duration',
        'service_price',
        'room_charge',
        'total_price',
        'promo_code',
        'discount_amount',
        'service_charge_amount',
        'tax_amount',
        'refund_amount',
        'guest_count',
        'status',
        'payment_status',
        'expires_at',
        'is_blocked',
        'block_reason',
        'notes',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by',
        'guest2_name',
        'guest2_whatsapp',
        'guest2_age_type',
        'guest2_age',
        'guest3_name',
        'guest3_whatsapp',
        'guest3_age_type',
        'guest3_age',
        'guest4_name',
        'guest4_whatsapp',
        'guest4_age_type',
        'guest4_age',
        'guest5_name',
        'guest5_whatsapp',
        'guest5_age_type',
        'guest5_age',
        'product_total',
        'nominal_dp'
    ];

    protected $appends = ['customer_name'];

    public function getCustomerNameAttribute()
    {
        return $this->user ? $this->user->name : ($this->guest_name ?? 'Tamu');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    protected $casts = [
        'booking_date' => 'date',
        'service_price' => 'decimal:2',
        'room_charge' => 'decimal:2',
        'product_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'nominal_dp' => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'duration' => 'integer',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_ref)) {
                $booking->booking_ref = 'BK-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class);
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function agendaLogs()
    {
        return $this->hasMany(BookingAgendaLog::class);
    }
}
