<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Prod\DokuPaymentMethodsSeeder;
use Database\Seeders\Prod\CountryDataSeeder;
use Database\Seeders\Prod\CitySeeder;
use Database\Seeders\Prod\LocationSeeder;
use Database\Seeders\Prod\BranchSeeder;
use Database\Seeders\Prod\AssetSeeder;
use Database\Seeders\Prod\RoleSeeder;
use Database\Seeders\Prod\SuperAdminSeeder;
use Database\Seeders\Prod\BranchAdminSeeder;
use Database\Seeders\Prod\NewsSeeder;
use Database\Seeders\Prod\ServiceSeeder;
use Database\Seeders\Prod\StaffSeeder;
use Database\Seeders\Prod\TherapistScheduleSeeder;
use Database\Seeders\Prod\CustomerSeeder;
use Database\Seeders\Prod\SupplierSeeder;
use Database\Seeders\Prod\ProductSeeder;
use Database\Seeders\Prod\CompanySettingsSeeder;

class ProdSeeder extends Seeder
{
    public function run()
    {
        // 1. Payment Methods
        $this->call(DokuPaymentMethodsSeeder::class);

        // 2. Locations (Country, City, Location)
        // CountryDataSeeder handles Provinces/Regencies
        $this->call(CountryDataSeeder::class);
        // CitySeeder handles Cities
        $this->call(CitySeeder::class);
        // LocationSeeder handles Districts/Villages
        $this->call(LocationSeeder::class);

        // 3. Structural (Roles, Branches, Settings, Assets)
        $this->call(RoleSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(AssetSeeder::class);
        $this->call(CompanySettingsSeeder::class);

        // 4. Administrative Users
        $this->call(SuperAdminSeeder::class);
        $this->call(BranchAdminSeeder::class);

        // 5. Content (News/Articles)
        $this->call(NewsSeeder::class);

        // 6. Services, Staff & Customers
        $this->call(ServiceSeeder::class);
        $this->call(StaffSeeder::class);
        $this->call(TherapistScheduleSeeder::class);
        $this->call(CustomerSeeder::class);

        // 7. Inventory
        $this->call(SupplierSeeder::class);
        $this->call(ProductSeeder::class);
    }
}
