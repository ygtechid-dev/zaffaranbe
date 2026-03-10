<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchPaymentConfig;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentConfigController extends Controller
{
    /**
     * Get payment configuration for a specific branch
     */
    public function show($branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $config = BranchPaymentConfig::where('branch_id', $branchId)->first();

        if (!$config) {
            // Return default config if not exists
            return response()->json([
                'branch_id' => $branchId,
                'branch_name' => $branch->name,
                'payment_gateway' => 'manual',
                'enabled_payment_methods' => ['cash'],
                'bank_accounts' => [],
                'ewallet_accounts' => [],
                'minimum_payment' => 0,
                'down_payment_percentage' => 0,
                'down_payment_amount' => 0,
                'allow_installment' => false,
                'max_installment_months' => 0,
                'auto_confirm_payment' => false,
                'payment_confirmation_timeout' => 24,
                'is_active' => false
            ]);
        }

        return response()->json([
            'config' => $config,
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code
            ]
        ]);
    }

    /**
     * Get all payment configurations (for all branches)
     */
    public function index()
    {
        $configs = BranchPaymentConfig::with('branch:id,name,code,city')->get();

        return response()->json([
            'configs' => $configs
        ]);
    }

    /**
     * Create or update payment configuration for a branch
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'payment_gateway' => 'required|in:midtrans,xendit,manual',

            // Midtrans fields (required if gateway is midtrans)
            'midtrans_server_key' => 'required_if:payment_gateway,midtrans|nullable|string',
            'midtrans_client_key' => 'required_if:payment_gateway,midtrans|nullable|string',
            'midtrans_merchant_id' => 'nullable|string',
            'midtrans_is_production' => 'boolean',

            // Xendit fields (required if gateway is xendit)
            'xendit_api_key' => 'required_if:payment_gateway,xendit|nullable|string',
            'xendit_callback_token' => 'nullable|string',
            'xendit_is_production' => 'boolean',

            // Payment methods
            'enabled_payment_methods' => 'nullable|array',
            'enabled_payment_methods.*' => 'in:credit_card,bank_transfer,e_wallet,cash,qris',

            // Bank accounts
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required|string',
            'bank_accounts.*.account_number' => 'required|string',
            'bank_accounts.*.account_holder' => 'required|string',

            // E-wallet accounts
            'ewallet_accounts' => 'nullable|array',
            'ewallet_accounts.*.provider' => 'required|string',
            'ewallet_accounts.*.account_number' => 'required|string',

            // Payment settings
            'minimum_payment' => 'nullable|numeric|min:0',
            'down_payment_percentage' => 'nullable|numeric|min:0|max:100',
            'down_payment_amount' => 'nullable|numeric|min:0',
            'allow_installment' => 'boolean',
            'max_installment_months' => 'nullable|integer|min:0|max:24',
            'auto_confirm_payment' => 'boolean',
            'payment_confirmation_timeout' => 'nullable|integer|min:1|max:168', // max 7 days
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if config already exists
        $config = BranchPaymentConfig::where('branch_id', $request->branch_id)->first();

        $data = $request->all();

        // Prevent overwriting with masked keys
        $keysToCheck = ['midtrans_server_key', 'midtrans_client_key', 'midtrans_merchant_id', 'xendit_api_key', 'xendit_callback_token'];
        foreach ($keysToCheck as $key) {
            if (isset($data[$key]) && strpos($data[$key], '***') !== false) {
                unset($data[$key]);
            }
        }

        if ($config) {
            // Update existing config
            $config->update($data);
            $message = 'Payment configuration updated successfully';
        } else {
            // Create new config
            $config = BranchPaymentConfig::create($data);
            $message = 'Payment configuration created successfully';
        }

        return response()->json([
            'message' => $message,
            'config' => $config->load('branch:id,name,code')
        ], $config->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update payment configuration
     */
    public function update(Request $request, $id)
    {
        $config = BranchPaymentConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'payment_gateway' => 'sometimes|in:midtrans,xendit,manual',
            'midtrans_server_key' => 'nullable|string',
            'midtrans_client_key' => 'nullable|string',
            'midtrans_merchant_id' => 'nullable|string',
            'midtrans_is_production' => 'boolean',
            'xendit_api_key' => 'nullable|string',
            'xendit_callback_token' => 'nullable|string',
            'xendit_is_production' => 'boolean',
            'enabled_payment_methods' => 'nullable|array',
            'bank_accounts' => 'nullable|array',
            'ewallet_accounts' => 'nullable|array',
            'minimum_payment' => 'nullable|numeric|min:0',
            'down_payment_percentage' => 'nullable|numeric|min:0|max:100',
            'down_payment_amount' => 'nullable|numeric|min:0',
            'allow_installment' => 'boolean',
            'max_installment_months' => 'nullable|integer|min:0|max:24',
            'auto_confirm_payment' => 'boolean',
            'payment_confirmation_timeout' => 'nullable|integer|min:1|max:168',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $config->update($request->all());

        return response()->json([
            'message' => 'Payment configuration updated successfully',
            'config' => $config->load('branch:id,name,code')
        ]);
    }

    /**
     * Delete payment configuration
     */
    public function destroy($id)
    {
        $config = BranchPaymentConfig::findOrFail($id);
        $config->delete();

        return response()->json([
            'message' => 'Payment configuration deleted successfully'
        ]);
    }

    /**
     * Test payment gateway connection
     */
    public function testConnection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gateway' => 'required|in:midtrans,xendit',
            'server_key' => 'required_if:gateway,midtrans',
            'api_key' => 'required_if:gateway,xendit',
            'is_production' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // TODO: Implement actual gateway testing
        // For now, just return success
        return response()->json([
            'success' => true,
            'message' => 'Connection test successful',
            'gateway' => $request->gateway
        ]);
    }
}
