<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::with('branches:id,name');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('condition') && $request->condition !== 'all') {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('branch_id') && $request->branch_id != 0 && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });
        }

        $assets = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 10));

        return response()->json($assets);
    }

    public function store(Request $request)
    {
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        $this->validate($request, [
            'name' => 'required|string|min:2|max:100|regex:/^[A-Za-z0-9\s]+$/',
            'category' => 'required|string|exists:asset_categories,name',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'purchase_price' => 'required|numeric|min:1|max:1000000000',
            'condition' => 'required|in:Baik,Perlu Perbaikan,Rusak',
            'status' => 'required|in:Aktif,Nonaktif',
            'is_global' => 'required|boolean',
            'branch_ids' => 'required_if:is_global,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'notes' => 'nullable|string|max:500',
            'last_maintenance' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->filled('purchase_date') && $value < $request->purchase_date) {
                        $fail('Maintenance terakhir tidak boleh sebelum tanggal pembelian.');
                    }
                },
            ],
        ]);

        $assetData = array_merge($request->only([
            'name',
            'category',
            'purchase_date',
            'purchase_price',
            'condition',
            'status',
            'notes',
            'last_maintenance',
            'is_global'
        ]));

        $asset = Asset::create($assetData);

        if (!$isGlobal && !empty($branchIds)) {
            $asset->branches()->sync($branchIds);
        }

        return response()->json($asset->load('branches'), 201);
    }

    public function show($id)
    {
        $asset = Asset::with('branches')->findOrFail($id);
        return response()->json($asset);
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $isGlobal = $request->input('is_global', $asset->is_global);
        $branchIds = $request->input('branch_ids', []);

        $this->validate($request, [
            'name' => 'sometimes|required|string|min:2|max:100|regex:/^[A-Za-z0-9\s]+$/',
            'category' => 'sometimes|required|string|exists:asset_categories,name',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'purchase_price' => 'sometimes|required|numeric|min:1|max:1000000000',
            'condition' => 'sometimes|required|in:Baik,Perlu Perbaikan,Rusak',
            'status' => 'sometimes|required|in:Aktif,Nonaktif',
            'is_global' => 'sometimes|required|boolean',
            'branch_ids' => 'required_if:is_global,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'notes' => 'nullable|string|max:500',
            'last_maintenance' => [
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) use ($request, $asset) {
                    $purchaseDate = $request->input('purchase_date', $asset->purchase_date);
                    if ($purchaseDate && $value < $purchaseDate) {
                        $fail('Maintenance terakhir tidak boleh sebelum tanggal pembelian.');
                    }
                },
            ],
        ]);

        $assetData = $request->only([
            'name',
            'category',
            'purchase_date',
            'purchase_price',
            'condition',
            'status',
            'notes',
            'last_maintenance',
            'is_global'
        ]);

        $asset->update($assetData);

        if ($isGlobal) {
            $asset->branches()->detach();
        } else if (!empty($branchIds)) {
            $asset->branches()->sync($branchIds);
        }

        return response()->json($asset->load('branches'));
    }

    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);
        $asset->delete();

        return response()->json(['message' => 'Asset deleted successfully']);
    }
}
