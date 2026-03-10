<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    protected $fillable = ['name', 'is_global', 'branch_id'];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'asset_category_branch');
    }
}
