<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $allServices = DB::table('services')->get();
    echo "Total Services in DB: " . count($allServices) . "\n";
    foreach ($allServices as $s) {
        $status = isset($s->is_active) ? ($s->is_active ? "Active" : "Inactive") : "N/A";
        echo "- ID: $s->id | Name: $s->name | Status: $status\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
