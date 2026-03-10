<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'day_of_week',
        'date',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'shift_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }
}
