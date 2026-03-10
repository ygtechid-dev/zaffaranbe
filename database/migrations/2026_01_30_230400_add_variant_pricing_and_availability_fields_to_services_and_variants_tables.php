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
            $table->boolean('all_branches_same_price')->default(true)->after('availability_data');
            $table->json('branch_prices')->nullable()->after('all_branches_same_price');
        });

        Schema::table('service_variants', function (Blueprint $table) {
            $table->json('branch_ids')->nullable()->after('capital_price');
            $table->boolean('all_branches_same_price')->default(true)->after('branch_ids');
            $table->json('branch_prices')->nullable()->after('all_branches_same_price');
            $table->boolean('is_limited_availability')->default(false)->after('branch_prices');
            $table->string('availability_type')->nullable()->after('is_limited_availability');
            $table->json('availability_data')->nullable()->after('availability_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['all_branches_same_price', 'branch_prices']);
        });

        Schema::table('service_variants', function (Blueprint $table) {
            $table->dropColumn([
                'branch_ids',
                'all_branches_same_price',
                'branch_prices',
                'is_limited_availability',
                'availability_type',
                'availability_data'
            ]);
        });
    }
};
