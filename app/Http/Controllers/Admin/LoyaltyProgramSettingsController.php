<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyProgramSettingsController extends Controller
{
    public function show(Request $request)
    {
        $branchId = $request->branch_id;
        $settings = DB::table('loyalty_program_settings')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId), fn($q) => $q->whereNull('branch_id'))
            ->first();

        if (!$settings) {
            return response()->json([
                'enabled' => true,
                'earningType' => 'amount_spent',
                'pointsPerAmount' => 1,
                'minOrderAmount' => 50000,
                'expiration' => 'After 1 Year',
                'applyMultiples' => true,
                'earnWhenRedeeming' => true,
                'channels' => ['All'],
                'customerGroups' => ['All'],
            ]);
        }

        return response()->json([
            'enabled' => (bool) $settings->enabled,
            'earningType' => $settings->earning_type,
            'pointsPerAmount' => $settings->points_per_amount,
            'minOrderAmount' => $settings->min_order_amount,
            'expiration' => $settings->expiration,
            'applyMultiples' => (bool) $settings->apply_multiples,
            'earnWhenRedeeming' => (bool) $settings->earn_when_redeeming,
            'channels' => json_decode($settings->channels) ?? ['All'],
            'customerGroups' => json_decode($settings->customer_groups) ?? ['All'],
        ]);
    }

    public function update(Request $request)
    {
        $branchId = $request->branch_id;
        $attrs = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        DB::table('loyalty_program_settings')->updateOrInsert($attrs, [
            'enabled' => $request->input('enabled', true),
            'earning_type' => $request->input('earningType', 'amount_spent'),
            'points_per_amount' => $request->input('pointsPerAmount', 1),
            'min_order_amount' => $request->input('minOrderAmount', 50000),
            'expiration' => $request->input('expiration', 'After 1 Year'),
            'apply_multiples' => $request->input('applyMultiples', true),
            'earn_when_redeeming' => $request->input('earnWhenRedeeming', true),
            'channels' => json_encode($request->input('channels', ['All'])),
            'customer_groups' => json_encode($request->input('customerGroups', ['All'])),
            'updated_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(['message' => 'Loyalty settings updated']);
    }
}
