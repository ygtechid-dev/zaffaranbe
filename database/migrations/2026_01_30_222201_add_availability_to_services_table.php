<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_limited_availability')->default(false)->after('is_active');
            $table->string('availability_type')->nullable()->after('is_limited_availability'); // 'specific_dates', 'recurring'
            $table->json('availability_data')->nullable()->after('availability_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['is_limited_availability', 'availability_type', 'availability_data']);
        });
    }
};
