<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['id', 'regency_id', 'name'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function regency()
    {
        return $this->belongsTo(Regency::class, 'regency_id');
    }

    public function villages()
    {
        return $this->hasMany(Village::class, 'district_id');
    }
}
