<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        return response()->json(Brand::with('branches')->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        // Handle single selection for compatibility
        if (isset($data['branch_id'])) {
            if ($data['branch_id'] == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$data['branch_id']];
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => 'required|string|unique:brands,name',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $brand = Brand::create([
            'name' => $request->name,
            'image' => $request->image,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null), // Keep legacy for now
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $brand->branches()->sync($branchIds);
        }

        return response()->json($brand->load('branches'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if (isset($data['branch_id'])) {
            if ($data['branch_id'] == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$data['branch_id']];
            }
        }

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => 'required|string|unique:brands,name,' . $id,
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $brand = Brand::findOrFail($id);
        $brand->update([
            'name' => $request->name,
            'image' => $request->image,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        if ($isGlobal) {
            $brand->branches()->detach();
        } else {
            $brand->branches()->sync($branchIds);
        }

        return response()->json($brand->load('branches'));
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->branches()->detach();
        $brand->delete();
        return response()->json(['message' => 'Merk berhasil dihapus']);
    }
}
