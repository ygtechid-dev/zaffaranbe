<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $table = 'notification_settings';

    protected $fillable = [
        'branch_id',
        'type',
        'settings'
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'settings' => 'array'
    ];
}
