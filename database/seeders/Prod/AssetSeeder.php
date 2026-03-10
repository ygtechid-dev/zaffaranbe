<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Asset Categories
        $categories = [
            ['name' => 'Elektronik', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Furniture', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Dekorasi', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Perlengkapan Spa', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Alat Kesehatan', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Kendaraan Ops', 'is_global' => true, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($categories as $category) {
            DB::table('asset_categories')->updateOrInsert(
                ['name' => $category['name']],
                $category
            );
        }

        // 2. Seed Assets
        $branches = DB::table('branches')->pluck('id')->toArray();
        if (empty($branches)) {
            $this->command->error('No branches found. Please run BranchSeeder first.');
            return;
        }

        $assets = [
            [
                'name' => 'AC Panasonic 2PK',
                'category' => 'Elektronik',
                'location' => 'Lobby Utama',
                'purchase_date' => '2023-05-10',
                'purchase_price' => 5500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Sofa Kulit 3 Seater',
                'category' => 'Furniture',
                'location' => 'Ruang Tunggu',
                'purchase_date' => '2023-06-15',
                'purchase_price' => 4500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Smart TV Samsung 55 Inch',
                'category' => 'Elektronik',
                'location' => 'Ruang Tunggu',
                'purchase_date' => '2023-07-20',
                'purchase_price' => 8000000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Meja Resepsionis Custom',
                'category' => 'Furniture',
                'location' => 'Lobby',
                'purchase_date' => '2023-05-01',
                'purchase_price' => 3500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Massage Bed Premium A1',
                'category' => 'Perlengkapan Spa',
                'location' => 'Room VVIP 1',
                'purchase_date' => '2023-08-10',
                'purchase_price' => 2500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Massage Bed Premium A2',
                'category' => 'Perlengkapan Spa',
                'location' => 'Room VVIP 1',
                'purchase_date' => '2023-08-10',
                'purchase_price' => 2500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Water Heater Ariston 30L',
                'category' => 'Elektronik',
                'location' => 'Kamar Mandi VVIP',
                'purchase_date' => '2023-09-05',
                'purchase_price' => 2200000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Diffuser Aromatherapy Industrial',
                'category' => 'Elektronik',
                'location' => 'Lobby',
                'purchase_date' => '2023-10-12',
                'purchase_price' => 1500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Set Dispenser Modena',
                'category' => 'Elektronik',
                'location' => 'Pantry',
                'purchase_date' => '2023-11-01',
                'purchase_price' => 2800000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Lukisan Dinding Zen 01',
                'category' => 'Dekorasi',
                'location' => 'Lobby',
                'purchase_date' => '2023-05-15',
                'purchase_price' => 1200000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => true, // Global asset
                'branch_id' => null,
            ],
            [
                'name' => 'Pot Bunga Keramik Besar',
                'category' => 'Dekorasi',
                'location' => 'Depan Pintu',
                'purchase_date' => '2023-05-20',
                'purchase_price' => 750000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[1] ?? $branches[0],
            ],
            [
                'name' => 'Laptop Admin HP Pavilion',
                'category' => 'Elektronik',
                'location' => 'Back Office',
                'purchase_date' => '2023-04-10',
                'purchase_price' => 9500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Printer Epson L3210',
                'category' => 'Elektronik',
                'location' => 'Resepsionis',
                'purchase_date' => '2023-04-12',
                'purchase_price' => 2400000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'CCTV Hikvision 8 Channel Set',
                'category' => 'Elektronik',
                'location' => 'Seluruh Area',
                'purchase_date' => '2023-03-25',
                'purchase_price' => 4800000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Kursi Kerja Staff Informa',
                'category' => 'Furniture',
                'location' => 'Back Office',
                'purchase_date' => '2023-06-01',
                'purchase_price' => 850000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Lemari Arsip Besi',
                'category' => 'Furniture',
                'location' => 'Back Office',
                'purchase_date' => '2023-06-01',
                'purchase_price' => 1750000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Motor Honda Vario 160 (Ops)',
                'category' => 'Kendaraan Ops',
                'location' => 'Parkiran',
                'purchase_date' => '2024-01-15',
                'purchase_price' => 28500000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Timbangan Digital Omron',
                'category' => 'Alat Kesehatan',
                'location' => 'Ruang Konsultasi',
                'purchase_date' => '2023-12-10',
                'purchase_price' => 450000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => true,
                'branch_id' => null,
            ],
            [
                'name' => 'Tensimeter Digital Onemed',
                'category' => 'Alat Kesehatan',
                'location' => 'Ruang Konsultasi',
                'purchase_date' => '2023-12-10',
                'purchase_price' => 350000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => true,
                'branch_id' => null,
            ],
            [
                'name' => 'Pemanas Handuk (Towel Warmer)',
                'category' => 'Perlengkapan Spa',
                'location' => 'Persiapan Area',
                'purchase_date' => '2023-08-20',
                'purchase_price' => 1800000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
            [
                'name' => 'Vacuum Cleaner Karcher',
                'category' => 'Elektronik',
                'location' => 'Storage Clean',
                'purchase_date' => '2023-07-05',
                'purchase_price' => 3200000,
                'condition' => 'Baik',
                'status' => 'Aktif',
                'is_global' => false,
                'branch_id' => $branches[0],
            ],
        ];

        foreach ($assets as $asset) {
            $branchId = $asset['branch_id'];
            unset($asset['branch_id']);

            $now = Carbon::now();
            $asset['created_at'] = $now;
            $asset['updated_at'] = $now;

            $assetId = DB::table('assets')->updateOrInsert(
                ['name' => $asset['name'], 'category' => $asset['category']],
                $asset
            );

            // If updateOrInsert doesn't return ID easily, we fetch it
            $insertedAsset = DB::table('assets')
                ->where('name', $asset['name'])
                ->where('category', $asset['category'])
                ->first();

            if ($insertedAsset && !$asset['is_global'] && $branchId) {
                DB::table('asset_branch')->updateOrInsert(
                    ['asset_id' => $insertedAsset->id, 'branch_id' => $branchId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
}
