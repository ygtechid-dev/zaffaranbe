<?php

namespace Database\Seeders\Prod;

use App\Models\Supplier;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        $branches = Branch::all();
        $firstBranch = $branches->first();

        $suppliers = [
            [
                'name' => 'PT Zafaran Kosmetik Indonesia',
                'code' => 'SUP-ZFR-001',
                'contact_person' => 'Budi Santoso',
                'phone' => '081234567891',
                'email' => 'sales@zafaran.id',
                'address' => 'Jl. Industri No. 12, Jakarta',
                'is_global' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Supplier Herbal Alami',
                'code' => 'SUP-HBL-002',
                'contact_person' => 'Siti Aminah',
                'phone' => '082234567892',
                'email' => 'info@herbalalami.co.id',
                'address' => 'Jl. Kebon Jeruk No. 45, Bandung',
                'is_global' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Indo Beauty Supplies',
                'code' => 'SUP-IBS-003',
                'contact_person' => 'Andi Wijaya',
                'phone' => '083334567893',
                'email' => 'contact@indobeauty.com',
                'address' => 'Ruko Sentra Bisnis Blok A/10, Surabaya',
                'is_global' => true,
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
