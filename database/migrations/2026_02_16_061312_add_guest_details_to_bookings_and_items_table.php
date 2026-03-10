<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'guest_age')) {
                $table->string('guest_age')->nullable()->after('guest_type');
            }
        });

        Schema::table('booking_items', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_items', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('end_time');
            }
            if (!Schema::hasColumn('booking_items', 'guest_phone')) {
                $table->string('guest_phone')->nullable()->after('guest_name');
            }
            if (!Schema::hasColumn('booking_items', 'guest_type')) {
                $table->string('guest_type')->nullable()->default('dewasa')->after('guest_phone');
            }
            if (!Schema::hasColumn('booking_items', 'guest_age')) {
                $table->string('guest_age')->nullable()->after('guest_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('guest_age');
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_phone', 'guest_type', 'guest_age']);
        });
    }
};
