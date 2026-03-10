<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CalendarSettings;
use App\Models\CompanySettings;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    /**
     * Get company settings, optionally by branch.
     * If no settings exist for the branch, try to return defaults or create a new record.
     */
    public function show(Request $request)
    {
        $branchId = $request->input('branch_id');

        $query = CompanySettings::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        $settings = $query->first();

        if (!$settings) {
            // If fetching for a specific branch and no settings exist,
            // we can either return 404 or return empty structure.
            // Returning 404 is valid if the client expects to handle it.
            // However, to be helpful, we can check if the branch exists.
            if ($branchId) {
                $branch = Branch::find($branchId);
                if (!$branch) {
                    return response()->json(['message' => 'Branch not found'], 404);
                }
                // Return null instead of 404 so frontend handles it gracefully without error logs
                return response()->json(null);
            }

            return response()->json(null);
        }

        return response()->json($settings);
    }

    /**
     * Update or Create company settings.
     */
    public function update(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'website' => 'nullable|url',
            'timezone' => 'nullable|string|in:WIB,WITA,WIT',
            'country' => 'nullable|string',
            'time_interval' => 'nullable|integer|min:1',
            'pos_time_interval' => 'nullable|integer|min:1',
            'payment_timeout' => 'nullable|integer|min:1',
            'min_dp' => 'nullable|numeric|min:0',
            'min_dp_type' => 'nullable|string|in:global,per_guest',
            'tax_percentage' => 'nullable|numeric|min:0',
            'service_charge_percentage' => 'nullable|numeric|min:0',
            'default_open_time' => 'nullable|string',
            'default_close_time' => 'nullable|string',
            'use_specific_operating_hours' => 'nullable|boolean',
            'register_enabled' => 'nullable|boolean',
            'commission_before_discount' => 'nullable|boolean',
            'commission_after_discount' => 'nullable|boolean',
            'commission_include_tax' => 'nullable|boolean',
            'assistant_commission' => 'nullable|string',
            'allow_unpaid_voucher_exchange' => 'nullable|boolean',
            'voucher_expiration' => 'nullable|string',
            'rounding_enabled' => 'nullable|boolean',
            'rounding_mode' => 'nullable|string|in:up,down',
            'rounding_amount' => 'nullable|integer|min:0',
            'is_tax_enabled' => 'nullable|boolean',
            'is_service_charge_enabled' => 'nullable|boolean',
        ]);

        $branchId = $request->input('branch_id');

        // Find existing or new
        $attributes = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        // If branch_id is null, we need to ensure we don't accidentally match a record with a branch_id
        // But updateOrCreate uses the first array for matching.
        // For NULL columns in SQL, simple equality checks might be tricky depending on driver, 
        // but Laravel handles `where('branch_id', null)` correctly.

        $settings = CompanySettings::updateOrCreate(
            $attributes,
            $request->except(['id', 'created_at', 'updated_at']) // Update all provided fields
        );

        // Auto-sync CalendarSettings start_hour/end_hour to match open/close times
        if ($request->has('default_open_time') || $request->has('default_close_time')) {
            $calAttr = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];
            $calSync = [];
            if ($request->has('default_open_time')) {
                $openParts = explode(':', $request->input('default_open_time') ?? '09:00');
                $calSync['start_hour'] = (int) ($openParts[0] ?? 9);
            }
            if ($request->has('default_close_time')) {
                $closeParts = explode(':', $request->input('default_close_time') ?? '22:00');
                $closeH = (int) ($closeParts[0] ?? 22);
                $closeM = (int) ($closeParts[1] ?? 0);
                $calSync['end_hour'] = $closeM > 0 ? $closeH + 1 : $closeH;
            }
            if (!empty($calSync)) {
                CalendarSettings::updateOrCreate($calAttr, $calSync);
            }
        }

        // Sync to Branch table for customer app compatibility
        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);
            if ($branch) {
                $branchData = [];
                if ($request->has('default_open_time')) {
                    $branchData['opening_time'] = $request->input('default_open_time');
                }
                if ($request->has('default_close_time')) {
                    $branchData['closing_time'] = $request->input('default_close_time');
                }
                
                // Final normalized operating_days for the branch
                $operatingDays = $request->input('operating_days');
                if (!$request->input('use_specific_operating_hours', true) && is_array($operatingDays)) {
                    $defaultOpen = $request->input('default_open_time');
                    $defaultClose = $request->input('default_close_time');
                    
                    $operatingDays = array_map(function($day) use ($defaultOpen, $defaultClose) {
                        if (is_array($day)) {
                            $day['open'] = $defaultOpen;
                            $day['close'] = $defaultClose;
                            return $day;
                        }
                        return $day; // For string formats if any
                    }, $operatingDays);
                }
                
                if ($request->has('operating_days')) {
                    $branchData['operating_days'] = $operatingDays;
                }
                
                if (!empty($branchData)) {
                    $branch->update($branchData);
                }
            }
        }

        return response()->json($settings);
    }

    /**
     * Update only intervals.
     */
    public function updateIntervals(Request $request)
    {
        $this->validate($request, [
            'branch_id' => 'nullable|exists:branches,id',
            'time_interval' => 'nullable|integer|min:1',
            'pos_time_interval' => 'required|integer|min:1',
        ]);

        $branchId = $request->input('branch_id');
        $attributes = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        $updateData = [
            'pos_time_interval' => $request->input('pos_time_interval'),
        ];

        if ($request->has('time_interval')) {
            $updateData['time_interval'] = $request->input('time_interval');
        }

        $settings = CompanySettings::updateOrCreate(
            $attributes,
            $updateData
        );

        return response()->json($settings);
    }
}
