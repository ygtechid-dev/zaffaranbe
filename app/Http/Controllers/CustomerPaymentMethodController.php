<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerPaymentMethodController extends Controller
{
    /**
     * Get all payment methods for current user
     */
    public function index(Request $request)
    {
        $methods = CustomerPaymentMethod::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($methods);
    }

    /**
     * Add a new payment method
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:bank_transfer,ewallet,card',
            'provider' => 'required|string|max:50',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:100',
            'card_last_four' => 'nullable|string|size:4',
            'card_brand' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;

        // If this is the first payment method, make it default
        $isFirst = CustomerPaymentMethod::where('user_id', $userId)->count() === 0;

        // If set as default, unset others
        if ($request->input('is_default', false) || $isFirst) {
            CustomerPaymentMethod::where('user_id', $userId)->update(['is_default' => false]);
        }

        $method = CustomerPaymentMethod::create([
            'user_id' => $userId,
            'type' => $request->type,
            'provider' => $request->provider,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'card_last_four' => $request->card_last_four,
            'card_brand' => $request->card_brand,
            'is_default' => $request->input('is_default', false) || $isFirst,
            'is_verified' => false, // Needs verification
        ]);

        return response()->json([
            'message' => 'Payment method added successfully',
            'payment_method' => $method
        ], 201);
    }

    /**
     * Update payment method
     */
    public function update(Request $request, $id)
    {
        $method = CustomerPaymentMethod::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'provider' => 'sometimes|string|max:50',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:100',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If set as default, unset others
        if ($request->input('is_default', false)) {
            CustomerPaymentMethod::where('user_id', $request->user()->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $method->update($request->only([
            'provider',
            'account_number',
            'account_name',
            'is_default'
        ]));

        return response()->json([
            'message' => 'Payment method updated',
            'payment_method' => $method->fresh()
        ]);
    }

    /**
     * Set as default payment method
     */
    public function setDefault(Request $request, $id)
    {
        $userId = $request->user()->id;

        $method = CustomerPaymentMethod::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        CustomerPaymentMethod::setDefault($userId, $id);

        return response()->json([
            'message' => 'Default payment method updated',
            'payment_method' => $method->fresh()
        ]);
    }

    /**
     * Delete payment method
     */
    public function destroy(Request $request, $id)
    {
        $method = CustomerPaymentMethod::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $wasDefault = $method->is_default;
        $method->delete();

        // If deleted was default, set another as default
        if ($wasDefault) {
            $first = CustomerPaymentMethod::where('user_id', $request->user()->id)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return response()->json(['message' => 'Payment method deleted']);
    }
}
