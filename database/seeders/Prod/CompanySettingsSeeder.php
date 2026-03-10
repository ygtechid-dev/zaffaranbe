<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompanySettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('company_settings')->truncate();

        $branches = DB::table('branches')->get();

        foreach ($branches as $branch) {
            DB::table('company_settings')->insert([
                'branch_id' => $branch->id,
                'payment_timeout' => 15,
                'min_dp' => 50000,
                'min_dp_type' => 'global',
                'tax_percentage' => 11,
                'service_charge_percentage' => 5,
                'pos_time_interval' => 15,
                'time_interval' => 15,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Also seed a global one (branch_id null or 0 depends on implementation)
        // From my observation in the controller, it seems to prefer branch_id matches.
    }
}
