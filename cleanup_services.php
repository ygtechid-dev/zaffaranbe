<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    $table = 'services';
    
    // Services mentioned by user in screenshot to be deleted
    $targetNames = [
        'Classic Manicure',
        'Deluxe Pedicure',
        'Hair Spa & Vitamin',
        'Scalp Treatment'
    ];

    echo "Cleaning up services in staging...\n";

    // 1. Delete by names (Hard Delete)
    $deletedByNames = DB::table($table)->whereIn('name', $targetNames)->delete();
    echo "Permanently deleted $deletedByNames services matching target names.\n";

    // 2. Check for soft delete column just in case
    if (Schema::hasColumn($table, 'deleted_at')) {
        $softDeletedCount = DB::table($table)->whereNotNull('deleted_at')->delete();
        echo "Permanently deleted $softDeletedCount services that were soft-deleted (found deleted_at column).\n";
    } else {
        echo "Column 'deleted_at' does not exist, skipping soft-delete cleanup.\n";
    }

    // 3. List remaining services
    $remaining = DB::table($table)->select('id', 'name', 'is_active')->get();
    echo "\nRemaining Services in Database:\n";
    foreach ($remaining as $s) {
        $status = $s->is_active ? "Active" : "Inactive";
        echo "- ID: $s->id | Name: $s->name | Status: $status\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
