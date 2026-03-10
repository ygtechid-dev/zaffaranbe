<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image', 'branch_id', 'is_global'];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'brand_branch');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
