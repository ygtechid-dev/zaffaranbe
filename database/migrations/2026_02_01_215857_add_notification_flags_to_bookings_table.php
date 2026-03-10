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
            $table->boolean('is_reminded_h1')->default(false);
            $table->boolean('is_reminded_h2')->default(false); // H-2 hours
            $table->boolean('is_review_requested')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['is_reminded_h1', 'is_reminded_h2', 'is_review_requested']);
        });
    }
};
