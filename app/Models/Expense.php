<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'branch_id',
        'cashier_shift_id',
        'category_id',
        'description',
        'amount',
        'receipt_image',
        'created_by'
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
