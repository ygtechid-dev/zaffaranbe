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
        Schema::table('therapists', function (Blueprint $table) {
            $table->enum('service_commission_type', ['percent', 'fixed'])->default('percent')->after('default_service_commission');
            $table->enum('product_commission_type', ['percent', 'fixed'])->default('percent')->after('default_product_commission');
        });

        // Migrate existing data
        DB::table('therapists')->update([
            'service_commission_type' => DB::raw('commission_type'),
            'product_commission_type' => DB::raw('commission_type'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn(['service_commission_type', 'product_commission_type']);
        });
    }
};
