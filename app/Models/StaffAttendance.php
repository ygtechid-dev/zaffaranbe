<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'branch_id',
        'check_in',
        'check_out',
        'status',
        'notes',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Therapist::class, 'staff_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Accessor for Duration String
    public function getDurationAttribute()
    {
        if (!$this->check_out) {
            return '-';
        }

        $diff = $this->check_in->diff($this->check_out);
        $parts = [];
        if ($diff->h > 0) $parts[] = $diff->h . ' Jam';
        if ($diff->i > 0) $parts[] = $diff->i . ' Menit';
        
        return implode(', ', $parts) ?: '0 Menit';
    }
}
