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

        if (!$settings) {
            return response()->json([
                'logoUrl' => '',
                'invoiceTitle' => 'INVOICE',
                'businessNameOverride' => '',
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
                'socialMedia' => ['instagram' => '', 'facebook' => '', 'twitter' => ''],
                'customHeader' => '',
                'customFooter' => ''
            ]);
        }

        return response()->json([
            'logoUrl' => $settings->logo_url ?? '',
            'invoiceTitle' => $settings->invoice_title ?? 'INVOICE',
            'businessNameOverride' => $settings->business_name_override ?? '',
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
            'customHeader' => $settings->custom_header ?? '',
            'customFooter' => $settings->custom_footer ?? ''
        ]);
    }

    public function update(Request $request)
    {
        $branchId = $request->branch_id;
        $attrs = $branchId ? ['branch_id' => $branchId] : ['branch_id' => null];

        // Ambil logo_url yang sudah ada di DB — jangan overwrite kalau request tidak kirim logoUrl
        $existing = DB::table('invoice_settings')->where($attrs)->first();
        $existingLogoUrl = $existing->logo_url ?? '';

        // Pakai logoUrl dari request kalau ada dan tidak kosong, fallback ke yang sudah ada di DB
        $logoUrl = $request->input('logoUrl') ?: $existingLogoUrl;

        DB::table('invoice_settings')->updateOrInsert($attrs, [
            'logo_url' => $logoUrl,
            'invoice_title' => $request->input('invoiceTitle', 'INVOICE'),
            'business_name_override' => $request->input('businessNameOverride', ''),
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

    public function uploadLogo(Request $request)
    {
        $this->validate($request, [
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:1024',
            'branch_id' => 'nullable',
        ]);

        $branchId = (int) $request->branch_id;
        $path = $request->file('logo')->store('invoice-logos', 'public');
        $url = config('app.url') . '/storage/' . $path;

        $attrs = ['branch_id' => $branchId];
        $exists = DB::table('invoice_settings')->where($attrs)->exists();

        \Log::info('Upload logo', [
            'branch_id' => $branchId,
            'url' => $url,
            'exists' => $exists,
        ]);

        if ($exists) {
            $affected = DB::table('invoice_settings')->where($attrs)->update([
                'logo_url' => $url,
                'updated_at' => \Carbon\Carbon::now(),
            ]);

            $check = DB::table('invoice_settings')->where($attrs)->first();
            \Log::info('After update check', [
                'affected' => $affected,
                'logo_url_in_db' => $check->logo_url,
            ]);
        } else {
            DB::table('invoice_settings')->insert([
                'branch_id' => $branchId,
                'logo_url' => $url,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);
        }

        return response()->json(['logo_url' => $url]);
    }
}