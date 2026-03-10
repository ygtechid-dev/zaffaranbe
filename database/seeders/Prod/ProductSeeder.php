<?php

namespace Database\Seeders\Prod;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductStock;
use App\Models\ProductVariantStock;
use App\Models\Brand;
use App\Models\Category as ProductCategory;
use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $branches = Branch::all();
        if ($branches->isEmpty()) {
            return;
        }

        $soloBranch = $branches->where('code', 'ZFR-SOLO')->first() ?: $branches->first();
        $jktBranch = $branches->where('code', 'ZFR-JKT')->first() ?: ($branches->count() > 1 ? $branches->get(1) : $branches->first());
        $supplier = Supplier::first();

        $products = [
            [
                'name' => 'Zafaran Facial Wash',
                'sku' => 'ZFR-FW-001',
                'category' => 'Skincare',
                'brand_name' => 'Zafaran',
                'retail_price' => 75000,
                'cost_price' => 45000,
                'description' => 'Sabun muka lembut untuk semua jenis kulit.',
                'is_global' => true,
                'variants' => []
            ],
            [
                'name' => 'Lavender Essential Oil',
                'sku' => 'ZFR-EO-LAV',
                'category' => 'Aromatherapy',
                'brand_name' => 'Natures',
                'retail_price' => 125000,
                'cost_price' => 80000,
                'description' => 'Minyak esensial lavender murni untuk relaksasi.',
                'is_global' => true,
                'variants' => []
            ],
            [
                'name' => 'Whitening Body Lotion',
                'sku' => 'ZFR-WBL-003',
                'category' => 'Body Care',
                'brand_name' => 'Zafaran',
                'retail_price' => 0, // Calculated from variants in some logic, but let's set a base
                'cost_price' => 0,
                'description' => 'Lotion pemutih badan dengan kandungan SPF 30.',
                'is_global' => true,
                'variants' => [
                    [
                        'name' => '100ml',
                        'sku' => 'ZFR-WBL-100',
                        'retail_price' => 85000,
                        'cost_price' => 50000,
                    ],
                    [
                        'name' => '250ml',
                        'sku' => 'ZFR-WBL-250',
                        'retail_price' => 150000,
                        'cost_price' => 90000,
                    ]
                ]
            ],
            [
                'name' => 'Zafaran Massage Oil (Lemongrass)',
                'sku' => 'ZFR-MO-LG',
                'category' => 'Massage',
                'brand_name' => 'Zafaran',
                'retail_price' => 95000,
                'cost_price' => 60000,
                'description' => 'Minyak pijat aromaterapi lemongrass.',
                'is_global' => false,
                'branch_id' => $soloBranch->id,
                'variants' => []
            ],
            [
                'name' => 'Natural Face Scrub',
                'sku' => 'ZFR-SCRB-01',
                'category' => 'Skincare',
                'brand_name' => 'Zafaran',
                'retail_price' => 65000,
                'cost_price' => 35000,
                'description' => 'Scrub wajah alami dengan butiran aprikot.',
                'is_global' => true,
                'variants' => []
            ],
            [
                'name' => 'Aromatic Candle - Sativa',
                'sku' => 'ZFR-AC-SAT',
                'category' => 'Home Fragrance',
                'brand_name' => 'Natures',
                'retail_price' => 110000,
                'cost_price' => 70000,
                'description' => 'Lilin aromaterapi dengan wangi sativa yang menenangkan.',
                'is_global' => true,
                'variants' => []
            ],
        ];

        foreach ($products as $pData) {
            $variants = $pData['variants'];
            unset($pData['variants']);

            // Mirror Brand and Category model
            if (isset($pData['brand_name'])) {
                Brand::firstOrCreate(['name' => $pData['brand_name']], ['is_global' => true]);
            }
            if (isset($pData['category'])) {
                ProductCategory::firstOrCreate(['name' => $pData['category']], ['is_global' => true]);
            }

            if (!isset($pData['supplier_id']) && $supplier) {
                $pData['supplier_id'] = $supplier->id;
            }

            $product = Product::updateOrCreate(
                ['sku' => $pData['sku']],
                $pData
            );

            if ($product->is_global) {
                $product->branches()->sync($branches->pluck('id'));
            } else {
                $product->branches()->sync([$pData['branch_id'] ?? $soloBranch->id]);
            }

            if (empty($variants)) {
                // Simple product stock
                ProductStock::updateOrCreate(
                    ['product_id' => $product->id, 'branch_id' => $soloBranch->id],
                    ['quantity' => rand(20, 50), 'location' => 'Gudang Utama', 'average_cost' => $pData['cost_price'] ?? 0]
                );

                ProductStock::updateOrCreate(
                    ['product_id' => $product->id, 'branch_id' => $jktBranch->id],
                    ['quantity' => rand(10, 30), 'location' => 'Gudang Utama', 'average_cost' => $pData['cost_price'] ?? 0]
                );
            } else {
                foreach ($variants as $vData) {
                    $vData['product_id'] = $product->id;
                    $variant = ProductVariant::updateOrCreate(
                        ['sku' => $vData['sku']],
                        $vData
                    );

                    ProductVariantStock::updateOrCreate(
                        ['product_variant_id' => $variant->id, 'branch_id' => $soloBranch->id],
                        ['quantity' => rand(15, 40), 'location' => 'Gudang Utama', 'average_cost' => $vData['cost_price'] ?? 0]
                    );

                    ProductVariantStock::updateOrCreate(
                        ['product_variant_id' => $variant->id, 'branch_id' => $jktBranch->id],
                        ['quantity' => rand(5, 20), 'location' => 'Gudang Utama', 'average_cost' => $vData['cost_price'] ?? 0]
                    );
                }
            }
        }
    }
}
