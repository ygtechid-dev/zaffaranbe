<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use Illuminate\Http\Request;

class PublicSettingsController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id');

        $settings = CompanySettings::when(
            $branchId,
            fn($q) => $q->where('branch_id', $branchId),
            fn($q) => $q->whereNull('branch_id')
        )->first();

        if (!$settings && $branchId) {
            $settings = CompanySettings::whereNull('branch_id')->first();
        }

        if (!$settings) {
            return response()->json([
                'tnc_enabled'               => true,
                'tnc_title'                 => 'Syarat & Ketentuan',
                'tnc_content'               => '',
                'tax_percentage'            => 0,
                'service_charge_percentage' => 0,
                'min_dp'                    => 0,
                'payment_timeout'           => 30,
            ]);
        }

        return response()->json([
            'tnc_enabled'               => (bool) ($settings->tnc_enabled ?? true),
            'tnc_title'                 => $settings->tnc_title ?? 'Syarat & Ketentuan',
            'tnc_content'               => $settings->tnc_content ?? '',
            'tax_percentage'            => (float) ($settings->tax_percentage ?? 0),
            'service_charge_percentage' => (float) ($settings->service_charge_percentage ?? 0),
            'min_dp'                    => (int) ($settings->min_dp ?? 0),
            'payment_timeout'           => (int) ($settings->payment_timeout ?? 30),
        ]);
    }
}
