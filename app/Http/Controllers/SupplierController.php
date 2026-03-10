<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::with('branches');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                  ->orWhereHas('branches', function($bq) use ($branchId) {
                      $bq->where('branches.id', $branchId);
                  });
            });
        }

        $suppliers = $query->latest()->get();

        return response()->json($suppliers);
    }

    public function store(Request $request)
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

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|unique:suppliers,code',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $supplier = Supplier::create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $supplier->branches()->sync($branchIds);
        }

        return response()->json(['message' => 'Supplier berhasil ditambahkan', 'data' => $supplier->load('branches')], 201);
    }

    public function show($id)
    {
        $supplier = Supplier::with('branches')->find($id);
        if (!$supplier)
            return response()->json(['message' => 'Supplier tidak ditemukan'], 404);
        return response()->json($supplier);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier)
            return response()->json(['message' => 'Supplier tidak ditemukan'], 404);

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

        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string|unique:suppliers,code,' . $id,
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $supplierData = array_merge($data, [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $supplier->update($supplierData);

        if ($isGlobal) {
            $supplier->branches()->detach();
        } else {
            $supplier->branches()->sync($branchIds);
        }

        return response()->json(['message' => 'Supplier berhasil diperbarui', 'data' => $supplier->load('branches')]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier)
            return response()->json(['message' => 'Supplier tidak ditemukan'], 404);
        $supplier->branches()->detach();
        $supplier->delete();
        return response()->json(['message' => 'Supplier berhasil dihapus']);
    }
}
