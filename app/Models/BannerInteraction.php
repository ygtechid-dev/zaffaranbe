<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerInteraction extends Model
{
    protected $fillable = [
        'banner_id',
        'user_id',
        'ip_address',
        'type',
    ];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
