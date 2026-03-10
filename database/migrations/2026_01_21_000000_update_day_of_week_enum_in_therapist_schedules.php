<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw statement to modify enum because Doctrine DBAL has issues with enums
        DB::statement("ALTER TABLE therapist_schedules MODIFY COLUMN day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'daily') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum (WARNING: 'daily' values will cause issues if present, usually would need cleanup first)
        // For safety in dev environment, we assume we can just revert struct, data loss for 'daily' is acceptable on rollback logic for now
        DB::statement("delete from therapist_schedules where day_of_week = 'daily'");
        DB::statement("ALTER TABLE therapist_schedules MODIFY COLUMN day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NULL");
    }
};
