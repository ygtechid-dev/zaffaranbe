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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('use_location_prices')->default(false)->after('is_active');
            $table->json('location_prices')->nullable()->after('use_location_prices');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('use_location_prices')->default(false)->after('is_active');
            $table->json('location_prices')->nullable()->after('use_location_prices');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['use_location_prices', 'location_prices']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['use_location_prices', 'location_prices']);
        });
    }
};
