<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CountryDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Seed Provinces
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('provinces')->truncate();
        DB::table('regencies')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $provinceFile = base_path('master data/provinces.csv');
        if (file_exists($provinceFile)) {
            $handle = fopen($provinceFile, "r");
            $header = fgetcsv($handle, 1000, ";"); // Skip header
            $provinces = [];
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                if (isset($data[0]) && isset($data[1])) {
                    $provinces[] = [
                        'id' => $data[0],
                        'name' => trim($data[1]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            fclose($handle);
            if (!empty($provinces)) {
                DB::table('provinces')->insertOrIgnore($provinces);
                $this->command->info('Provinces seeded: ' . count($provinces));
            }
        }

        // Seed Regencies
        $regencyFile = base_path('master data/regencies.csv');
        if (file_exists($regencyFile)) {
            $handle = fopen($regencyFile, "r");
            $header = fgetcsv($handle, 1000, ";"); // Skip header
            $regencies = [];
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                if (isset($data[0]) && isset($data[1]) && isset($data[2])) {
                    $regencies[] = [
                        'id' => $data[0],
                        'province_id' => $data[1],
                        'name' => trim($data[2]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    // Batch insert to avoid memory issues if large
                    if (count($regencies) >= 100) {
                        DB::table('regencies')->insertOrIgnore($regencies);
                        $regencies = [];
                    }
                }
            }
            fclose($handle);
            if (!empty($regencies)) {
                DB::table('regencies')->insertOrIgnore($regencies);
            }
            $this->command->info('Regencies seeded.');
        }
    }
}
