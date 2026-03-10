<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    protected $fillable = ['id', 'province_id', 'name'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}
