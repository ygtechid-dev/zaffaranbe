<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'branch_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
