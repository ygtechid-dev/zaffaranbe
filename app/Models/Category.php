<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'branch_id', 'is_global'];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'category_branch');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
