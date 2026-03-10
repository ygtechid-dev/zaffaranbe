<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Get notification settings for a branch
     */
    public function show(Request $request)
    {
        $branchId = $request->input('branch_id');

        // Fetch all setting types
        $query = NotificationSetting::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        $allSettings = $query->get()->pluck('settings', 'type');

        // Transform if necessary to match frontend expected structure
        // Frontend expects one object with keys: agenda, inventory, etc.
        // Each key contains the settings object.

        $result = [];
        foreach ($allSettings as $type => $settings) {
            $result[$type] = $settings;
        }

        return response()->json($result ?: new \stdClass());
    }

    /**
     * Update notification settings
     */
    public function update(Request $request)
    {
        $branchId = $request->input('branch_id');
        $data = $request->except(['branch_id']);

        // Data contains keys like 'agenda', 'inventory' which are the types.
        // Each value is the settings array.

        foreach ($data as $type => $settings) {
            if (is_array($settings)) {
                NotificationSetting::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'type' => $type
                    ],
                    [
                        'settings' => $settings
                    ]
                );
            }
        }

        // Fetch updated settings to return
        return $this->show($request);
    }
}
