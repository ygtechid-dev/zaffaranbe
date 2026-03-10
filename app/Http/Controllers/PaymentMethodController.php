<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentMethod::with('branches')->where('is_active', true);

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                  ->orWhereHas('branches', function($bq) use ($branchId) {
                      $bq->where('branches.id', $branchId);
                  });
            });
        }

        $paymentMethods = $query->orderBy('sort_order')->get();

        return response()->json(['data' => $paymentMethods, 'debug' => 'v2']);
    }

    public function all(Request $request)
    {
        $query = PaymentMethod::with('branches');

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                  ->orWhereHas('branches', function($bq) use ($branchId) {
                      $bq->where('branches.id', $branchId);
                  });
            });
        }

        $paymentMethods = $query->orderBy('sort_order')->get();
        return response()->json($paymentMethods);
    }

    public function store(Request $request)
    {
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:payment_methods,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,digital,bank,qris,transfer,edc,ewallet',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'sort_order' => 'integer|min:0',
            'fee' => 'nullable|numeric|min:0',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $paymentMethod = PaymentMethod::create([
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'icon' => $request->icon,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'sort_order' => $request->sort_order ?? 0,
            'fee' => $request->fee ?? 0,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'is_global' => $isGlobal,
            'is_online' => $request->is_online ?? false,
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $paymentMethod->branches()->sync($branchIds);
        }

        AuditLog::log('create', 'Pembayaran', "Created payment method: {$paymentMethod->name} ({$paymentMethod->code})");

        return response()->json([
            'message' => 'Payment method created successfully',
            'data' => $paymentMethod->load('branches')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:payment_methods,code,' . $id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,digital,bank,qris,transfer,edc,ewallet',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'sort_order' => 'integer|min:0',
            'fee' => 'nullable|numeric|min:0',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $paymentMethod->update([
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'icon' => $request->icon,
            'description' => $request->description,
            'is_active' => $request->is_active ?? $paymentMethod->is_active,
            'sort_order' => $request->sort_order ?? $paymentMethod->sort_order,
            'fee' => $request->fee ?? $paymentMethod->fee,
            'account_number' => $request->account_number ?? $paymentMethod->account_number,
            'account_name' => $request->account_name ?? $paymentMethod->account_name,
            'is_global' => $isGlobal,
            'is_online' => $request->is_online ?? $paymentMethod->is_online,
        ]);

        if ($isGlobal) {
            $paymentMethod->branches()->detach();
        } else {
            $paymentMethod->branches()->sync($branchIds);
        }

        AuditLog::log('update', 'Pembayaran', "Updated payment method: {$paymentMethod->name}");

        return response()->json([
            'message' => 'Payment method updated successfully',
            'data' => $paymentMethod->load('branches')
        ]);
    }

    public function destroy($id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $name = $paymentMethod->name;
        $paymentMethod->branches()->detach();
        $paymentMethod->delete();

        AuditLog::log('delete', 'Pembayaran', "Deleted payment method: {$name}");

        return response()->json([
            'message' => 'Payment method deleted successfully'
        ]);
    }

    public function toggleActive($id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active
        ]);

        AuditLog::log('update', 'Pembayaran', "Toggled status for payment method: {$paymentMethod->name} to " . ($paymentMethod->is_active ? 'active' : 'inactive'));

        return response()->json([
            'message' => 'Payment method status updated successfully',
            'data' => $paymentMethod->load('branches')
        ]);
    }
}

