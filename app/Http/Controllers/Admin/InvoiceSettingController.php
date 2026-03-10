<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceSettingController extends Controller
{
    public function show(Request $request)
    {
        $branchId = $request->branch_id;
        $settings = DB::table('invoice_settings')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId), fn($q) => $q->whereNull('branch_id'))
            ->first();

        // Default layout matching Frontend state
        if (!$settings) {
            return response()->json([
                'autoPrint' => true,
                'showCustomerName' => true,
                'showCustomerContact' => false,
                'showCustomerAddress' => false,
                'showLogo' => true,
                'showItemDetails' => true,
                'showItemPrice' => false,
                'showStaffName' => true,
                'showTax' => true,
                'hideCompanyName' => false,
                'hideCashierName' => false,
                'applyToAllLocations' => true,
                'socialMedia' => [
                    'instagram' => '',
                    'facebook' => '',
                    'twitter' => ''
                ],
                'customHeader' => '',
                'customFooter' => ''
            ]);
        }

        return response()->json([
            'autoPrint' => (bool) $settings->auto_print,
            'showCustomerName' => (bool) $settings->show_customer_name,
            'showCustomerContact' => (bool) $settings->show_customer_contact,
            'showCustomerAddress' => (bool) $settings->show_customer_address,
            'showLogo' => (bool) $settings->show_logo,
            'showItemDetails' => (bool) $settings->show_item_details,
            'showItemPrice' => (bool) $settings->show_item_price,
            'showStaffName' => (bool) $settings->show_staff_name,
            'showTax' => (bool) $settings->show_tax,
            'hideCompanyName' => (bool) $settings->hide_company_name,
            'hideCashierName' => (bool) $settings->hide_cashier_name,
            'applyToAllLocations' => (bool) $settings->apply_to_all_locations,
            'socialMedia' => json_decode($settings->social_media, true) ?? ['instagram' => '', 'facebook' => '', 'twitter' => ''],
            'customHeader' => $settings->custom_header,
            'customFooter' => $settings->custom_footer
        ]);
    }

    public function update(Request $request)
    {
        $branchId = $request->branch_id;
        $attrs = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        DB::table('invoice_settings')->updateOrInsert($attrs, [
            'auto_print' => $request->input('autoPrint', true),
            'show_customer_name' => $request->input('showCustomerName', true),
            'show_customer_contact' => $request->input('showCustomerContact', false),
            'show_customer_address' => $request->input('showCustomerAddress', false),
            'show_logo' => $request->input('showLogo', true),
            'show_item_details' => $request->input('showItemDetails', true),
            'show_item_price' => $request->input('showItemPrice', false),
            'show_staff_name' => $request->input('showStaffName', true),
            'show_tax' => $request->input('showTax', true),
            'hide_company_name' => $request->input('hideCompanyName', false),
            'hide_cashier_name' => $request->input('hideCashierName', false),
            'apply_to_all_locations' => $request->input('applyToAllLocations', true),
            'social_media' => json_encode($request->input('socialMedia', ['instagram' => '', 'facebook' => '', 'twitter' => ''])),
            'custom_header' => $request->input('customHeader', ''),
            'custom_footer' => $request->input('customFooter', ''),
            'updated_at' => \Carbon\Carbon::now(),
            'created_at' => \Carbon\Carbon::now(),
        ]);

        return response()->json(['message' => 'Invoice settings updated']);
    }
}
