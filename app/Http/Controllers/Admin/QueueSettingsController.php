<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueSettingsController extends Controller
{
    public function show(Request $request)
    {
        $branchId = $request->branch_id;
        $settings = DB::table('queue_settings')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId), fn($q) => $q->whereNull('branch_id'))
            ->first();

        if (!$settings) {
            return response()->json([
                'theme' => ['ongoingBg' => '#FF4500', 'ongoingText' => '#000000'],
                'kiosk' => ['maxServices' => 'unlimited', 'requireNamePhone' => true],
                'queueMode' => ['enabled' => false]
            ]);
        }

        return response()->json([
            'theme' => ['ongoingBg' => $settings->ongoing_bg_color, 'ongoingText' => $settings->ongoing_text_color],
            'kiosk' => ['maxServices' => $settings->max_services, 'requireNamePhone' => (bool) $settings->require_name_phone],
            'queueMode' => ['enabled' => (bool) $settings->queue_mode_enabled]
        ]);
    }

    public function update(Request $request)
    {
        $branchId = $request->branch_id;
        $attrs = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        DB::table('queue_settings')->updateOrInsert($attrs, [
            'ongoing_bg_color' => $request->input('theme.ongoingBg', '#FF4500'),
            'ongoing_text_color' => $request->input('theme.ongoingText', '#000000'),
            'max_services' => $request->input('kiosk.maxServices', 'unlimited'),
            'require_name_phone' => $request->input('kiosk.requireNamePhone', true),
            'queue_mode_enabled' => $request->input('queueMode.enabled', false),
            'updated_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(['message' => 'Queue settings updated']);
    }
}
