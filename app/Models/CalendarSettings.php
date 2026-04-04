<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarSettings extends Model
{
    protected $table = 'calendar_settings';

    protected $fillable = [
        'branch_id',
        'start_hour',
        'end_hour',
        'slot_duration',
         'reschedule_interval',
        'therapist_buffer_time',
        'default_view',
        'agenda_color',
        'week_start',
        'staff_order',
        'allow_reschedule',
        'reschedule_deadline'
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'start_hour' => 'integer',
        'end_hour' => 'integer',
        'slot_duration' => 'integer',
        'therapist_buffer_time' => 'integer',
          'reschedule_interval' => 'integer', 
        'allow_reschedule' => 'boolean',
        'reschedule_deadline' => 'integer'
    ];
}
