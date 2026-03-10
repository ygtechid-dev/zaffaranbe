<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'branch', 'user', 'supplier']);

        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('type_group')) {
            $typeGroup = $request->type_group;
            if ($typeGroup === 'orders') {
                $query->whereIn('movement_type', ['in']);
            } elseif ($typeGroup === 'opname') {
                $query->whereIn('movement_type', ['opname', 'adjustment']);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($request->get('limit') === 'none' || $request->get('all') === 'true') {
            return response()->json($query->latest('movement_date')->get());
        }
        $perPage = $request->get('limit', 15);
        return response()->json($query->latest('movement_date')->paginate(is_numeric($perPage) ? (int)$perPage : 15));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'movement_type' => 'required|in:in,out,adjustment,transfer,return,opname',
            'quantity' => 'required|integer',
            'cost_price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'reference' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'status' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ]);

        $productId = $request->product_id;
        $branchId = $request->branch_id;
        $qty = $request->quantity;

        // Get current stock
        $stock = \App\Models\ProductStock::firstOrCreate(
            ['product_id' => $productId, 'branch_id' => $branchId],
            ['quantity' => 0, 'average_cost' => 0]
        );

        $before = $stock->quantity;
        
        if ($request->movement_type === 'out' || ($request->movement_type === 'transfer' && $request->is_source)) {
            $after = $before - abs($qty);
            $finalQty = -abs($qty);
        } elseif (in_array($request->movement_type, ['opname', 'adjustment'])) {
            $after = $before + $qty;
            $finalQty = $qty;
        } else {
            $after = $before + abs($qty);
            $finalQty = abs($qty);
        }

        $movement = StockMovement::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'user_id' => $request->user()->id ?? null,
            'movement_type' => $request->movement_type,
            'quantity' => $finalQty,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'cost_price' => $request->cost_price ?? 0,
            'description' => $request->description,
            'reference' => $request->reference,
            'supplier_id' => $request->supplier_id,
            'status' => $request->status ?? 'completed',
            'movement_date' => $request->movement_date ?? now(),
        ]);

        // Update stock
        $stock->update(['quantity' => $after]);

        return response()->json([
            'message' => 'Stock movement recorded successfully',
            'movement' => $movement->load(['product', 'branch'])
        ]);
    }
}
