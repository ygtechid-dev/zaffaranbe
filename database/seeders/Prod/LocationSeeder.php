<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LocationSeeder extends Seeder
{
    public function run()
    {
        // Provinces & Regencies already handled by CountryDataSeeder
        // $this->seedProvinces();
        // $this->seedRegencies();
        $this->seedDistricts();
        $this->seedVillages();
    }

    private function seedProvinces()
    {
        $path = base_path('master data/provinces.csv');
        $this->importCsv($path, 'provinces', ['id', 'name']);
    }

    private function seedRegencies()
    {
        $path = base_path('master data/regencies.csv');
        $this->importCsv($path, 'regencies', ['id', 'province_id', 'name']);
    }

    private function seedDistricts()
    {
        $path = base_path('master data/districts.csv');
        $this->importCsv($path, 'districts', ['id', 'regency_id', 'name']);
    }

    private function seedVillages()
    {
        $path = base_path('master data/villages.csv');
        $this->importCsv($path, 'villages', ['id', 'district_id', 'name']);
    }

    private function importCsv($path, $table, $columns)
    {
        if (!File::exists($path)) {
            $this->command->error("File not found: $path");
            return;
        }

        $this->command->info("Seeding $table...");
        
        $file = fopen($path, 'r');
        $header = fgetcsv($file, 0, ';'); // Skip header

        $data = [];
        $count = 0;
        $batchSize = 1000;

        while (($row = fgetcsv($file, 0, ';')) !== FALSE) {
            if (count($row) < count($columns)) continue;
            
            $item = [];
            foreach ($columns as $index => $column) {
                $item[$column] = $row[$index];
            }
            $item['created_at'] = date('Y-m-d H:i:s');
            $item['updated_at'] = date('Y-m-d H:i:s');
            $data[] = $item;
            $count++;

            if (count($data) >= $batchSize) {
                DB::table($table)->insert($data);
                $data = [];
            }
        }

        if (count($data) > 0) {
            DB::table($table)->insert($data);
        }

        fclose($file);
        $this->command->info("Seeded $count records into $table.");
    }
}
