<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'membership_type',
        'invoice_no',
        'invoice_date',
        'start_date',
        'expiry_date',
        'status',
        'price',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getStatusLabelAttribute()
    {
        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return 'Expired';
        }
        return $this->status ?? 'Active';
    }
}
