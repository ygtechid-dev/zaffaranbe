<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change user_id to nullable
        // Since we don't have doctrine/dbal, we use raw SQL
        DB::statement('ALTER TABLE bookings MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert user_id to not nullable
        // Note: This might fail if there are NULL user_ids.
        try {
            DB::statement('ALTER TABLE bookings MODIFY user_id BIGINT UNSIGNED NOT NULL');
        } catch (\Exception $e) {
            // Ignore if data exists
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_phone']);
        });
    }
};
