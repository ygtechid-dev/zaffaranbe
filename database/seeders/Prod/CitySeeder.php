<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Path relative to project root
        $csvPath = base_path('database/templates/indonesian_cities_coordinates.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");
            return;
        }

        $this->command->info("Reading cities from: $csvPath");

        $handle = fopen($csvPath, "r");
        if ($handle === FALSE) {
            $this->command->error("Failed to open CSV file.");
            return;
        }

        // Header: City,Province,Latitude,Longitude
        $header = fgetcsv($handle);

        $cities = [];
        $now = Carbon::now();

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // data[0] = City
            // data[1] = Province
            // data[2] = Latitude
            // data[3] = Longitude

            $cities[] = [
                'name' => $data[0],
                'province' => $data[1],
                'latitude' => $data[2],
                'longitude' => $data[3],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        if (!empty($cities)) {
            // Truncate first to avoid duplicates if re-seeding
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('cities')->truncate();
            DB::table('cities')->insert($cities);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->command->info('Cities seeded successfully: ' . count($cities));
        } else {
            $this->command->info('No cities found in CSV.');
        }
    }
}
