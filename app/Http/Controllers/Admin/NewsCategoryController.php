<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsCategoryController extends Controller
{
    public function index()
    {
        return response()->json(NewsCategory::with('branches')->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $isGlobal = $request->input('is_global', true);
        $branchIds = $request->input('branch_ids', []);

        $validator = Validator::make($data, [
            'name' => 'required|string|unique:news_categories,name',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = NewsCategory::create([
            'name' => $data['name'],
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'), 201);
    }

    public function update(Request $request, $id)
    {
        $category = NewsCategory::findOrFail($id);
        $data = $request->all();
        $isGlobal = $request->input('is_global', true);
        $branchIds = $request->input('branch_ids', []);

        $validator = Validator::make($data, [
            'name' => 'required|string|unique:news_categories,name,' . $id,
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update([
            'name' => $data['name'],
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
        $category = NewsCategory::findOrFail($id);
        $category->branches()->detach();
        $category->delete();
        return response()->json(['message' => 'Kategori artikel berhasil dihapus']);
    }
}
