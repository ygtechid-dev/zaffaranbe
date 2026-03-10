<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE assets MODIFY COLUMN category VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        // Warning: This might fail if there are values in 'category' that are not in the enum list
        DB::statement("ALTER TABLE assets MODIFY COLUMN category ENUM('furniture', 'equipment', 'electronic', 'other') DEFAULT 'other'");
    }
};
