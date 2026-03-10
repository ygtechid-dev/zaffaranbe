<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        return response()->json(ServiceCategory::with('branches')->orderBy('position', 'asc')->get());
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:service_categories,id',
            'categories.*.position' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->categories as $item) {
            ServiceCategory::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Urutan kategori berhasil diperbarui']);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $isGlobal = filter_var($request->input('is_global', true), FILTER_VALIDATE_BOOLEAN);
        $branchIds = $request->input('branch_ids', []);

        $validator = Validator::make($data, [
            'name' => 'required|string|unique:service_categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $maxPosition = ServiceCategory::max('position');

        $category = ServiceCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
            'position' => $maxPosition + 1,
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'), 201);
    }

    public function update(Request $request, $id)
    {
        $category = ServiceCategory::findOrFail($id);
        $data = $request->all();
        $isGlobal = filter_var($request->input('is_global', true), FILTER_VALIDATE_BOOLEAN);
        $branchIds = $request->input('branch_ids', []);

        $validator = Validator::make($data, [
            'name' => 'required|string|unique:service_categories,name,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldName = $category->name;

        $category->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        // Sync the legacy 'category' text column on all related services
        if ($oldName !== $data['name']) {
            \App\Models\Service::where('service_category_id', $id)->update(['category' => $data['name']]);
        }

        if ($isGlobal) {
            $category->branches()->detach();
        } else {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'));
    }

    public function destroy($id)
    {
        $category = ServiceCategory::findOrFail($id);
        $category->branches()->detach();
        $category->delete();
        return response()->json(['message' => 'Kategori layanan berhasil dihapus']);
    }

    public function uploadImage(Request $request, $id)
    {
        $category = ServiceCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'service_category_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store in public/uploads/service_categories directory
            $uploadPath = base_path('public/uploads/service_categories');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $path = $file->move($uploadPath, $filename);

            // Update category with image path
            $imageUrl = '/uploads/service_categories/' . $filename;
            $category->update(['image' => $imageUrl]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'image_url' => $imageUrl,
                'category' => $category->fresh()
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }
}
