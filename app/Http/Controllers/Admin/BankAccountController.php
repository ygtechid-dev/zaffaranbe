<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('bank_payment_configs')->where('is_active', true);
        if ($request->branch_id) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)->orWhereNull('branch_id');
            });
        }
        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ]);

        $id = DB::table('bank_payment_configs')->insertGetId([
            'branch_id' => $request->branch_id,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'branch' => $request->branch,
            'is_active' => true,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(DB::table('bank_payment_configs')->find($id), 201);
    }

    public function destroy($id)
    {
        DB::table('bank_payment_configs')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
