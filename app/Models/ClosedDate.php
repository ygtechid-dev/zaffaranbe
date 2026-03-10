<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosedDate extends Model
{
    protected $table = 'closed_dates';

    protected $fillable = [
        'branch_id',
        'name',
        'start_date',
        'end_date',
        'reason'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
