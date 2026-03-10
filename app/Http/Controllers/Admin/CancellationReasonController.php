<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CancellationReasonController extends Controller
{
    public function index()
    {
        $reasons = DB::table('cancellation_reasons')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        return response()->json($reasons);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'reason' => 'required|string|max:255',
        ]);

        $id = DB::table('cancellation_reasons')->insertGetId([
            'reason' => $request->reason,
            'is_active' => true,
            'sort_order' => DB::table('cancellation_reasons')->count() + 1,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(DB::table('cancellation_reasons')->find($id), 201);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'reason' => 'required|string|max:255',
        ]);

        DB::table('cancellation_reasons')->where('id', $id)->update([
            'reason' => $request->reason,
            'updated_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(DB::table('cancellation_reasons')->find($id));
    }

    public function destroy($id)
    {
        DB::table('cancellation_reasons')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
