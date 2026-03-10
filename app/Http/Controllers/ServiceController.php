<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\ServicePriceLog;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query();

        // Default filter is_active to true if not specified
        $isActive = $request->has('is_active') 
            ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) 
            : true;
        
        $query->where('services.is_active', $isActive);

        // Filter is_booking_online_enabled
        if ($request->has('is_booking_online_enabled')) {
            $isEnabled = filter_var($request->is_booking_online_enabled, FILTER_VALIDATE_BOOLEAN);
            $query->where('services.is_booking_online_enabled', $isEnabled);
        }

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('services.is_global', true)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });
        }

        if ($request->has('category') && $request->category !== 'All') {
            $query->where('services.category', $request->category);
        }

        if ($request->has('service_category_id')) {
            $query->where('services.service_category_id', $request->service_category_id);
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('services.name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('services.description', 'like', '%' . $searchTerm . '%')
                    ->orWhere('services.code', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('variants', function ($vq) use ($searchTerm) {
                        $vq->where('name', 'like', '%' . $searchTerm . '%');
                    });
            });
        }

        $query = $query->with(['branches', 'variants', 'serviceCategory'])
            ->leftJoin('service_categories as cat_pos', 'services.service_category_id', '=', 'cat_pos.id')
            ->select('services.*', \DB::raw('COALESCE(cat_pos.name, services.category) as category'))
            ->orderByRaw('CASE WHEN cat_pos.position IS NULL THEN 9999 ELSE cat_pos.position END ASC')
            ->orderByRaw('CASE WHEN services.position IS NULL OR services.position = 0 THEN 9999 ELSE services.position END ASC')
            ->orderBy('services.name', 'asc');

        $perPage = $request->input('per_page', 10);
        if ($request->input('limit') === 'none' || $request->input('all') === 'true') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'services' => 'required|array',
            'services.*.id' => 'required|exists:services,id',
            'services.*.position' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->services as $item) {
            Service::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Urutan layanan berhasil diperbarui']);
    }

    public function show($id)
    {
        // Allow admin to view inactive services
        $service = Service::with(['branches', 'variants'])->findOrFail($id);
        return response()->json($service);
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
            'name' => 'required|string|min:3|max:100',
            'code' => 'required|string|unique:services,code',
            'category' => 'required|string',
            'service_category_id' => 'nullable|exists:service_categories,id',
            'gender' => 'nullable|in:all,male,female',
            'duration' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
            'is_booking_online_enabled' => 'nullable|boolean',
            'requires_room' => 'nullable|boolean',
            'is_limited_availability' => 'nullable|boolean',
            'availability_type' => 'nullable|string|in:specific_dates,recurring',
            'availability_data' => 'nullable|array',
            'all_branches_same_price' => 'nullable|boolean',
            'branch_prices' => 'nullable|array',
            'branch_ids' => 'required_if:is_global,false|array|min:1',
            'variants' => 'nullable|array',
            'variants.*.duration' => 'required|integer|min:1',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.special_price' => 'nullable|numeric|min:0',
            'variants.*.capital_price' => 'nullable|numeric|min:0',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.branch_ids' => 'nullable|array',
            'variants.*.all_branches_same_price' => 'nullable|boolean',
            'variants.*.branch_prices' => 'nullable|array',
            'variants.*.is_limited_availability' => 'nullable|boolean',
            'variants.*.availability_type' => 'nullable|string|in:specific_dates,recurring',
            'variants.*.availability_data' => 'nullable|array',
        ], [
            'name.required' => 'Nama layanan wajib diisi.',
            'name.min' => 'Nama layanan minimal 3 karakter.',
            'name.max' => 'Nama layanan maksimal 100 karakter.',
            'category.required' => 'Kategori layanan wajib dipilih.',
            'duration.required' => 'Durasi wajib diisi.',
            'duration.min' => 'Durasi harus antara 5–480 menit.',
            'price.required' => 'Harga layanan wajib diisi.',
            'branch_ids.required_if' => 'Pilih minimal satu cabang.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $maxPosition = Service::where('category', $request->category)->max('position');
        $serviceData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'position' => $maxPosition + 1
        ]);
        $service = Service::create($serviceData);

        if (!$isGlobal && !empty($branchIds)) {
            $service->branches()->sync($branchIds);
        }

        if ($request->has('variants')) {
            foreach ($request->variants as $variant) {
                $v = $service->variants()->create($variant);
                
                // Log initial variant price
                ServicePriceLog::create([
                    'service_id' => $service->id,
                    'service_variant_id' => $v->id,
                    'old_price' => 0,
                    'new_price' => $v->price,
                    'price_type' => 'regular',
                    'changed_by' => Auth::id(),
                    'notes' => 'Harga awal varian'
                ]);

                if ($v->special_price > 0) {
                    ServicePriceLog::create([
                        'service_id' => $service->id,
                        'service_variant_id' => $v->id,
                        'old_price' => 0,
                        'new_price' => $v->special_price,
                        'price_type' => 'special',
                        'changed_by' => Auth::id(),
                        'notes' => 'Harga spesial awal varian'
                    ]);
                }
            }
        }

        // Log initial service price
        ServicePriceLog::create([
            'service_id' => $service->id,
            'old_price' => 0,
            'new_price' => $service->price,
            'price_type' => 'regular',
            'changed_by' => Auth::id(),
            'notes' => 'Harga awal layanan'
        ]);

        if ($service->special_price > 0) {
            ServicePriceLog::create([
                'service_id' => $service->id,
                'old_price' => 0,
                'new_price' => $service->special_price,
                'price_type' => 'special',
                'changed_by' => Auth::id(),
                'notes' => 'Harga spesial awal layanan'
            ]);
        }

        AuditLog::log('create', 'Layanan', "Created service: {$service->name} ({$service->code})");

        return response()->json($service->load(['branches', 'variants']), 201);
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $isGlobal = $request->input('is_global', $service->is_global);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|min:3|max:100',
            'code' => 'sometimes|required|string|unique:services,code,' . $id,
            'category' => 'sometimes|required|string',
            'service_category_id' => 'nullable|exists:service_categories,id',
            'gender' => 'nullable|in:all,male,female',
            'duration' => 'sometimes|required|integer|min:5|max:480',
            'price' => 'sometimes|required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|required|boolean',
            'is_booking_online_enabled' => 'nullable|boolean',
            'requires_room' => 'nullable|boolean',
            'is_limited_availability' => 'nullable|boolean',
            'availability_type' => 'nullable|string|in:specific_dates,recurring',
            'availability_data' => 'nullable|array',
            'all_branches_same_price' => 'nullable|boolean',
            'branch_prices' => 'nullable|array',
            'branch_ids' => 'sometimes|array|min:1',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer|exists:service_variants,id',
            'variants.*.duration' => 'required_with:variants|integer|min:1',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.special_price' => 'nullable|numeric|min:0',
            'variants.*.capital_price' => 'nullable|numeric|min:0',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.branch_ids' => 'nullable|array',
            'variants.*.all_branches_same_price' => 'nullable|boolean',
            'variants.*.branch_prices' => 'nullable|array',
            'variants.*.is_limited_availability' => 'nullable|boolean',
            'variants.*.availability_type' => 'nullable|string|in:specific_dates,recurring',
            'variants.*.availability_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldPrice = $service->price;
        $oldSpecialPrice = $service->special_price;

        $serviceData = array_merge($request->all(), ['is_global' => $isGlobal]);
        $service->update($serviceData);

        // Log service price change
        if ($oldPrice != $service->price) {
            ServicePriceLog::create([
                'service_id' => $service->id,
                'old_price' => $oldPrice,
                'new_price' => $service->price,
                'price_type' => 'regular',
                'changed_by' => Auth::id(),
                'notes' => 'Perubahan harga layanan'
            ]);
        }
        if ($oldSpecialPrice != $service->special_price) {
            ServicePriceLog::create([
                'service_id' => $service->id,
                'old_price' => $oldSpecialPrice,
                'new_price' => $service->special_price,
                'price_type' => 'special',
                'changed_by' => Auth::id(),
                'notes' => 'Perubahan harga spesial layanan'
            ]);
        }

        if ($isGlobal) {
            $service->branches()->detach();
        } else if (!empty($branchIds)) {
            $service->branches()->sync($branchIds);
        }

        if ($request->has('variants')) {
            $existingIds = [];
            foreach ($request->variants as $variantData) {
                if (isset($variantData['id'])) {
                    $variant = $service->variants()->find($variantData['id']);
                    if ($variant) {
                        $vOldPrice = $variant->price;
                        $vOldSpecialPrice = $variant->special_price;
                        
                        $variant->update($variantData);
                        $existingIds[] = $variantData['id'];

                        // Log variant price change
                        if ($vOldPrice != $variant->price) {
                            ServicePriceLog::create([
                                'service_id' => $service->id,
                                'service_variant_id' => $variant->id,
                                'old_price' => $vOldPrice,
                                'new_price' => $variant->price,
                                'price_type' => 'regular',
                                'changed_by' => Auth::id(),
                                'notes' => 'Perubahan harga varian'
                            ]);
                        }
                        if ($vOldSpecialPrice != $variant->special_price) {
                            ServicePriceLog::create([
                                'service_id' => $service->id,
                                'service_variant_id' => $variant->id,
                                'old_price' => $vOldSpecialPrice,
                                'new_price' => $variant->special_price,
                                'price_type' => 'special',
                                'changed_by' => Auth::id(),
                                'notes' => 'Perubahan harga spesial varian'
                            ]);
                        }
                    }
                } else {
                    $newVariant = $service->variants()->create($variantData);
                    $existingIds[] = $newVariant->id;

                    // Log initial new variant price
                    ServicePriceLog::create([
                        'service_id' => $service->id,
                        'service_variant_id' => $newVariant->id,
                        'old_price' => 0,
                        'new_price' => $newVariant->price,
                        'price_type' => 'regular',
                        'changed_by' => Auth::id(),
                        'notes' => 'Harga awal varian baru'
                    ]);
                }
            }
            // Delete removed variants
            $service->variants()->whereNotIn('id', $existingIds)->delete();
        }

        AuditLog::log('update', 'Layanan', "Updated service: {$service->name}");

        return response()->json($service->load(['branches', 'variants']));
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $name = $service->name;
        $service->branches()->detach();
        $service->delete();

        AuditLog::log('delete', 'Layanan', "Deleted service: {$name}");
        return response()->json(['message' => 'Service deleted successfully']);
    }

    public function allPriceLogs(Request $request)
    {
        $query = ServicePriceLog::with(['service', 'variant', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        if ($request->has('variant_id')) {
            $query->where('service_variant_id', $request->variant_id);
        }

        if ($request->input('all') === 'true' || $request->input('limit') === 'none') {
            return response()->json($query->get());
        }

        $perPage = $request->input('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    public function priceLogs($id)
    {
        $logs = ServicePriceLog::with(['service', 'variant', 'user'])
            ->where('service_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function uploadImage(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'service_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store in public/uploads/services directory
            $uploadPath = base_path('public/uploads/services');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $path = $file->move($uploadPath, $filename);

            // Update service with image path
            $imageUrl = '/uploads/services/' . $filename;
            $service->update(['image' => $imageUrl]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'image_url' => $imageUrl,
                'service' => $service->fresh()
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }
}
