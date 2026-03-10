<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('branches')->latest()->get());
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

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => 'required|string|unique:categories,name',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'));
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
            'name' => 'required|string|unique:categories,name,' . $id,
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = Category::findOrFail($id);
        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        if ($isGlobal) {
            $category->branches()->detach();
        } else {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'));
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->branches()->detach();
        $category->delete();
        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
