<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsCategory extends Model
{
    protected $fillable = ['name', 'is_global', 'branch_id'];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'news_category_branch');
    }
}
