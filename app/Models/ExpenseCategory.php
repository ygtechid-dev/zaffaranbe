<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'type', 'icon', 'description', 'branch_id', 'is_global', 'is_active'];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'expense_category_branch');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
