<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\AuditLog;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        $query = Product::with(['supplier', 'branches', 'variants', 'variants.stocks']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;

            // Filter products: Global products OR products belonging to this branch
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                    ->orWhere('branch_id', $branchId)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });

            // Eager load stock only for specific branch if requested
            $query->with([
                'stocks' => function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                },
                'variants' => function ($q) {
                    $q->latest();
                },
                'variants.stocks' => function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            ]);
        } else {
            $query->with(['stocks', 'variants', 'variants.stocks']);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('brand')) {
            $query->where('brand_name', $request->brand);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('min_stock')) {
            $query->whereHas('stocks', function ($q) use ($request) {
                $q->where('quantity', '>=', $request->min_stock);
                if ($request->filled('branch_id') && $request->branch_id !== 'all') {
                    $q->where('branch_id', $request->branch_id);
                }
            });
        }

        if ($request->has('max_stock')) {
            $query->whereHas('stocks', function ($q) use ($request) {
                $q->where('quantity', '<=', $request->max_stock);
                if ($request->filled('branch_id') && $request->branch_id !== 'all') {
                    $q->where('branch_id', $request->branch_id);
                }
            });
        }

        if ($request->has('is_selling')) {
            $isSelling = filter_var($request->is_selling, FILTER_VALIDATE_BOOLEAN);
            if ($isSelling) {
                $query->where('is_active', true);
            }
        }

        if ($request->has('is_booking_online_enabled')) {
            $isBookingOnline = filter_var($request->is_booking_online_enabled, FILTER_VALIDATE_BOOLEAN);
            if ($isBookingOnline) {
                $query->where('is_booking_online_enabled', true);
            }
        }

        if ($request->get('limit') === 'none' || $request->get('all') === 'true') {
            $products = $query->latest()->get();
        } else {
            $perPage = $request->get('limit', 10);
            $products = $query->latest()->paginate(is_numeric($perPage) ? (int)$perPage : 10);
        }

        // Add computed fields to each product
        $products->transform(function ($product) use ($request) {
            $branchId = $request->get('branch_id');

            // Calculate Retail Price logic
            $computedPrice = $product->retail_price;

            // 1. Handle Simple Product Location Price
            if (!$product->hasVariants() && $product->use_location_prices && $branchId && !empty($product->location_prices)) {
                if (isset($product->location_prices[$branchId]['retail'])) {
                    $computedPrice = $product->location_prices[$branchId]['retail'];
                } elseif (isset($product->location_prices[(string) $branchId]['retail'])) {
                    $computedPrice = $product->location_prices[(string) $branchId]['retail'];
                }
            }
            // 2. Handle Variant Location Price (heuristic: use first variant for main product price)
            elseif ($product->hasVariants()) {
                $firstVariant = $product->variants->first();
                if ($firstVariant) {
                    $computedPrice = $firstVariant->retail_price;

                    if ($firstVariant->use_location_prices && $branchId && !empty($firstVariant->location_prices)) {
                        if (isset($firstVariant->location_prices[$branchId]['retail'])) {
                            $computedPrice = $firstVariant->location_prices[$branchId]['retail'];
                        } elseif (isset($firstVariant->location_prices[(string) $branchId]['retail'])) {
                            $computedPrice = $firstVariant->location_prices[(string) $branchId]['retail'];
                        }
                    }
                } else {
                    $computedPrice = 0;
                }

                // Explicitly set computed attributes for each variant
                foreach ($product->variants as $variant) {
                    $vPrice = $variant->retail_price;
                    if ($variant->use_location_prices && $branchId && !empty($variant->location_prices)) {
                        if (isset($variant->location_prices[$branchId]['retail'])) {
                            $vPrice = $variant->location_prices[$branchId]['retail'];
                        } elseif (isset($variant->location_prices[(string) $branchId]['retail'])) {
                            $vPrice = $variant->location_prices[(string) $branchId]['retail'];
                        }
                    }
                    $variant->setAttribute('computed_retail_price', (float) $vPrice);
                    $variant->setAttribute('total_stock', $variant->getTotalStockAttribute());
                }
            }

            // Access the accessor and set as new attribute (override with calculated one)
            $product->setAttribute('computed_retail_price', (float) $computedPrice);
            $product->setAttribute('total_stock', $product->getTotalStockAttribute());
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Store a newly created product in storage.
     */
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
            'sku' => 'required|string|unique:products,sku',
            'code' => 'nullable|string|unique:products,code',
            'brand_name' => 'nullable|string',
            'category' => 'nullable|string',
            'retail_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'initial_stock' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Check if product has variants
            $hasVariants = !empty($data['variants']) && is_array($data['variants']) && count($data['variants']) > 0;

            // Convert empty strings to null for unique columns
            $code = isset($data['code']) && $data['code'] !== '' ? $data['code'] : null;

            $product = Product::create([
                'name' => $data['name'] ?? null,
                'sku' => $data['sku'] ?? null,
                'code' => $code,
                'brand_name' => $data['brand_name'] ?? null,
                'category' => $data['category'] ?? null,
                'description' => isset($data['description']) && $data['description'] !== '' ? $data['description'] : null,
                // If has variants, retail_price can be 0 (price is on variants)
                'retail_price' => $hasVariants ? 0 : ($data['retail_price'] ?? 0),
                'special_price' => $hasVariants ? 0 : ($data['special_price'] ?? 0),
                'cost_price' => $hasVariants ? 0 : ($data['cost_price'] ?? 0),
                'use_location_prices' => $hasVariants ? false : ($data['use_location_prices'] ?? false),
                'location_prices' => $hasVariants ? null : ($data['location_prices'] ?? null),
                'is_global' => $isGlobal,
                'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
                'supplier_id' => isset($data['supplier_id']) && $data['supplier_id'] !== '' ? $data['supplier_id'] : null,
                'image' => $data['image'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'is_booking_online_enabled' => $request->boolean('is_booking_online_enabled', true),
                'reorder_point' => $data['reorder_point'] ?? 10,
            ]);

            if (!$isGlobal && !empty($branchIds)) {
                $product->branches()->sync($branchIds);
            }

            // Resolve stock branch ID fallback logic
            $stockBranchId = $request->input('branch_id') ?: ($branchIds[0] ?? null);
            if (!$stockBranchId) {
                $firstBranch = \App\Models\Branch::first();
                $stockBranchId = $firstBranch ? $firstBranch->id : null;
            }

            // Handle variants if provided
            if ($hasVariants) {
                foreach ($data['variants'] as $variantData) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $variantData['name'],
                        'sku' => $variantData['sku'] ?? ($product->sku . '-' . uniqid()),
                        'retail_price' => $variantData['retail_price'] ?? $variantData['price'] ?? 0,
                        'special_price' => $variantData['special_price'] ?? null,
                        'cost_price' => $variantData['cost_price'] ?? 0,
                        'track_stock' => $variantData['track_stock'] ?? true,
                        'is_active' => true,
                        'use_location_prices' => $variantData['use_location_prices'] ?? false,
                        'location_prices' => $variantData['location_prices'] ?? null,
                        'image' => $variantData['image'] ?? null,
                    ]);

                    // Handle initial stock for variant
                    $variantInitialStock = isset($variantData['initial_stock']) ? (int) $variantData['initial_stock'] : 0;

                    if ($variantInitialStock > 0 && $stockBranchId) {
                        ProductVariantStock::create([
                            'product_variant_id' => $variant->id,
                            'branch_id' => $stockBranchId,
                            'location' => $request->location ?? 'Gudang Utama',
                            'quantity' => $variantInitialStock,
                            'average_cost' => $variant->cost_price,
                        ]);
                    }
                }
            } else {
                // Handle initial stock for product without variants
                $initialStock = $request->input('initial_stock');
                if ($initialStock !== null && $stockBranchId && (int) $initialStock > 0) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'branch_id' => $stockBranchId,
                        'location' => $request->location ?? 'Gudang Utama',
                        'quantity' => (int) $initialStock,
                        'average_cost' => $product->cost_price ?? 0
                    ]);

                    // Create initial movement log
                    StockMovement::create([
                        'product_id' => $product->id,
                        'branch_id' => $stockBranchId,
                        'user_id' => auth()->id(),
                        'movement_type' => 'in',
                        'quantity' => (int) $initialStock,
                        'quantity_before' => 0,
                        'quantity_after' => (int) $initialStock,
                        'cost_price' => $product->cost_price ?? 0,
                        'description' => 'Initial stock on creation',
                        'movement_date' => \Carbon\Carbon::now()
                    ]);
                }
            }

            DB::commit();

            // Reload with relationships
            $product->load(['branches', 'variants.stocks', 'stocks']);

            // Add computed fields
            $product->setAttribute('computed_retail_price', $product->getComputedRetailPriceAttribute());
            $product->setAttribute('total_stock', $product->getTotalStockAttribute());

            return response()->json([
                'message' => 'Produk berhasil dibuat',
                'data' => $product
            ], 201);

            AuditLog::log('create', 'Inventori', "Created product: {$product->name} (SKU: {$product->sku})");

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membuat produk', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::with(['stocks.branch', 'supplier', 'branches', 'variants.stocks.branch'])->find($id);
        if (!$product)
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);

        // Add computed fields
        $product->setAttribute('computed_retail_price', $product->getComputedRetailPriceAttribute());
        $product->setAttribute('total_stock', $product->getTotalStockAttribute());

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if (!$product)
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);

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
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|unique:products,sku,' . $id,
            'code' => 'sometimes|nullable|string|unique:products,code,' . $id,
            'retail_price' => 'sometimes|numeric|min:0',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Check if product has variants
            $hasVariants = !empty($data['variants']) && is_array($data['variants']) && count($data['variants']) > 0;

            // Convert empty strings to null for unique columns
            $code = isset($data['code']) && $data['code'] !== '' ? $data['code'] : null;
            $description = isset($data['description']) && $data['description'] !== '' ? $data['description'] : null;
            $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' ? $data['supplier_id'] : null;

            $productData = array_merge($data, [
                'code' => $code,
                'description' => $description,
                'supplier_id' => $supplierId,
                'is_global' => $isGlobal,
                'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
                // If has variants, retail_price can be 0 (price is on variants)
                'retail_price' => $hasVariants ? 0 : ($data['retail_price'] ?? $product->retail_price),
                'special_price' => $hasVariants ? 0 : ($data['special_price'] ?? $product->special_price),
                'cost_price' => $hasVariants ? 0 : ($data['cost_price'] ?? $product->cost_price),
                'use_location_prices' => $hasVariants ? false : ($data['use_location_prices'] ?? $product->use_location_prices),
                'location_prices' => $hasVariants ? null : ($data['location_prices'] ?? $product->location_prices),
                'is_booking_online_enabled' => $request->boolean('is_booking_online_enabled', $product->is_booking_online_enabled),
            ]);

            // Remove variants from product data to avoid mass assignment error
            unset($productData['variants']);

            $product->update($productData);

            if ($isGlobal) {
                $product->branches()->detach();
            } else {
                $product->branches()->sync($branchIds);
            }

            // Handle variants if provided
            if ($hasVariants) {
                // Get existing variant IDs
                $existingVariantIds = $product->variants->pluck('id')->toArray();
                $submittedVariantIds = [];

                // Resolve branch for stock
                $stockBranchId = $request->input('branch_id') ?: ($branchIds[0] ?? null);
                if (!$stockBranchId) {
                    $firstBranch = \App\Models\Branch::first();
                    $stockBranchId = $firstBranch ? $firstBranch->id : null;
                }

                foreach ($data['variants'] as $variantData) {
                    $variant = null;
                    // If variant has an ID and it's numeric (existing variant), update it
                    if (isset($variantData['id']) && is_numeric($variantData['id']) && in_array($variantData['id'], $existingVariantIds)) {
                        $variant = ProductVariant::find($variantData['id']);
                        if ($variant) {
                            $variant->update([
                                'name' => $variantData['name'],
                                'retail_price' => $variantData['retail_price'] ?? $variantData['price'] ?? 0,
                                'special_price' => $variantData['special_price'] ?? null,
                                'cost_price' => $variantData['cost_price'] ?? 0,
                                'track_stock' => $variantData['track_stock'] ?? true,
                                'use_location_prices' => $variantData['use_location_prices'] ?? false,
                                'location_prices' => $variantData['location_prices'] ?? null,
                                'image' => $variantData['image'] ?? null,
                            ]);
                            $submittedVariantIds[] = $variant->id;
                        }
                    } else {
                        // New variant, create it
                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $variantData['name'],
                            'sku' => $variantData['sku'] ?? ($product->sku . '-' . uniqid()),
                            'retail_price' => $variantData['retail_price'] ?? $variantData['price'] ?? 0,
                            'special_price' => $variantData['special_price'] ?? null,
                            'cost_price' => $variantData['cost_price'] ?? 0,
                            'track_stock' => $variantData['track_stock'] ?? true,
                            'is_active' => true,
                            'use_location_prices' => $variantData['use_location_prices'] ?? false,
                            'location_prices' => $variantData['location_prices'] ?? null,
                            'image' => $variantData['image'] ?? null,
                        ]);
                        $submittedVariantIds[] = $variant->id;
                    }

                    // Handle stock update for this variant if initial_stock is provided
                    if ($variant && isset($variantData['initial_stock']) && $stockBranchId) {
                        $variantQty = (int) $variantData['initial_stock'];

                        // We don't log movements for variants here yet for simplicity, but we update the stock
                        ProductVariantStock::updateOrCreate(
                            ['product_variant_id' => $variant->id, 'branch_id' => $stockBranchId],
                            [
                                'quantity' => $variantQty,
                                'location' => $request->location ?? 'Gudang Utama',
                                'average_cost' => $variant->cost_price
                            ]
                        );
                    }
                }

                // Delete variants that were removed (not in submitted list)
                $variantsToDelete = array_diff($existingVariantIds, $submittedVariantIds);
                if (!empty($variantsToDelete)) {
                    ProductVariant::whereIn('id', $variantsToDelete)->delete();
                }
            } else {
                // Handle stock for product without variants
                $stockBranchId = $request->input('branch_id') ?: ($branchIds[0] ?? null);
                if (!$stockBranchId) {
                    $firstBranch = \App\Models\Branch::first();
                    $stockBranchId = $firstBranch ? $firstBranch->id : null;
                }

                if ($request->has('initial_stock') && $stockBranchId) {
                    $newQuantity = (int) $request->initial_stock;
                    $oldStock = ProductStock::where(['product_id' => $product->id, 'branch_id' => $stockBranchId])->first();
                    $quantityBefore = $oldStock ? $oldStock->quantity : 0;

                    ProductStock::updateOrCreate(
                        ['product_id' => $product->id, 'branch_id' => $stockBranchId],
                        [
                            'quantity' => $newQuantity,
                            'location' => $request->location ?? 'Gudang Utama',
                            'average_cost' => $product->cost_price ?? 0
                        ]
                    );

                    if ($newQuantity != $quantityBefore) {
                        StockMovement::create([
                            'product_id' => $product->id,
                            'branch_id' => $stockBranchId,
                            'user_id' => auth()->id(),
                            'movement_type' => 'adjustment',
                            'quantity' => abs($newQuantity - $quantityBefore),
                            'quantity_before' => $quantityBefore,
                            'quantity_after' => $newQuantity,
                            'cost_price' => $product->cost_price ?? 0,
                            'description' => 'Stock adjustment during product update',
                            'movement_date' => \Carbon\Carbon::now()
                        ]);
                    }
                }
            }

            DB::commit();

            // Reload with relationships
            $product->load(['branches', 'variants.stocks', 'stocks']);

            // Add computed fields
            $product->setAttribute('computed_retail_price', $product->getComputedRetailPriceAttribute());
            $product->setAttribute('total_stock', $product->getTotalStockAttribute());

            return response()->json([
                'message' => 'Produk berhasil diperbarui',
                'data' => $product
            ]);

            AuditLog::log('update', 'Inventori', "Updated product: {$product->name} (SKU: {$product->sku})");

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui produk', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product)
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);

        $name = $product->name;
        $product->delete();
        AuditLog::log('delete', 'Inventori', "Deleted product: {$name}");
        return response()->json(['message' => 'Produk berhasil dihapus']);
    }

    public function updateBrand(Request $request)
    {
        $data = $request->all();
        if (isset($data['branch_id']) && $data['branch_id'] == 0) {
            $data['branch_id'] = null;
        }

        $validator = Validator::make($data, [
            'old_name' => 'required|string',
            'new_name' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Update products usage
            Product::where('brand_name', $request->old_name)->update(['brand_name' => $request->new_name]);

            // Update Master Brand
            $brandData = ['name' => $request->new_name];
            // Only update branch_id if present in request (handle 0 as null)
            if (isset($data['branch_id']) || array_key_exists('branch_id', $data)) {
                $brandData['branch_id'] = $data['branch_id'];
            }

            $newBrand = \App\Models\Brand::where('name', $request->new_name)->first();
            $oldBrand = \App\Models\Brand::where('name', $request->old_name)->first();

            if ($oldBrand) {
                if ($newBrand && $newBrand->id !== $oldBrand->id) {
                    // New name already exists as a different record, delete old one and keep new one
                    $oldBrand->delete();
                } else {
                    // Update existing record
                    $oldBrand->update($brandData);
                }
            } else if (!$newBrand) {
                // Brand didn't exist in master, and new brand name doesn't exist either, create it
                \App\Models\Brand::create($brandData);
            }

            DB::commit();
            AuditLog::log('update', 'Inventori', "Updated brand name from {$request->old_name} to {$request->new_name}");
            return response()->json(['message' => 'Merk berhasil diperbarui']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui merk', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteBrand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            Product::where('brand_name', $request->name)->update(['brand_name' => null]);
            \App\Models\Brand::where('name', $request->name)->delete();

            DB::commit();
            AuditLog::log('delete', 'Inventori', "Deleted brand: {$request->name}");
            return response()->json(['message' => 'Merk berhasil dihapus']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus merk', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $request)
    {
        $data = $request->all();
        if (isset($data['branch_id']) && $data['branch_id'] == 0) {
            $data['branch_id'] = null;
        }

        $validator = Validator::make($data, [
            'old_name' => 'required|string',
            'new_name' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            Product::where('category', $request->old_name)->update(['category' => $request->new_name]);

            $catData = ['name' => $request->new_name];
            if (isset($data['branch_id']) || array_key_exists('branch_id', $data)) {
                $catData['branch_id'] = $data['branch_id'];
            }

            $newCategory = \App\Models\Category::where('name', $request->new_name)->first();
            $oldCategory = \App\Models\Category::where('name', $request->old_name)->first();

            if ($oldCategory) {
                if ($newCategory && $newCategory->id !== $oldCategory->id) {
                    // Merge: delete old, new already exists
                    $oldCategory->delete();
                } else {
                    $oldCategory->update($catData);
                }
            } else if (!$newCategory) {
                \App\Models\Category::create($catData);
            }

            DB::commit();
            AuditLog::log('update', 'Inventori', "Updated category name from {$request->old_name} to {$request->new_name}");
            return response()->json(['message' => 'Kategori berhasil diperbarui']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui kategori', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            Product::where('category', $request->name)->update(['category' => null]);
            \App\Models\Category::where('name', $request->name)->delete();

            DB::commit();
            AuditLog::log('delete', 'Inventori', "Deleted category: {$request->name}");
            return response()->json(['message' => 'Kategori berhasil dihapus']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus kategori', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadImage(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'product_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();

            $uploadPath = base_path('public/uploads/products');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $file->move($uploadPath, $filename);

            $imageUrl = '/uploads/products/' . $filename;
            $product->update(['image' => $imageUrl]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'image_url' => $imageUrl,
                'product' => $product->fresh()
            ]);
        }

        return response()->json(['error' => 'No image provided'], 400);
    }
}
