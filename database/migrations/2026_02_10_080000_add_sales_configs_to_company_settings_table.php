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
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('commission_before_discount')->default(false);
            $table->boolean('commission_after_discount')->default(false);
            $table->boolean('commission_include_tax')->default(true);
            $table->boolean('allow_unpaid_voucher_exchange')->default(true);
            $table->boolean('register_enabled')->default(true);
            $table->boolean('rounding_enabled')->default(true);
            $table->string('rounding_mode')->default('up');
            $table->integer('rounding_amount')->default(100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'commission_before_discount',
                'commission_after_discount',
                'commission_include_tax',
                'allow_unpaid_voucher_exchange',
                'register_enabled',
                'rounding_enabled',
                'rounding_mode',
                'rounding_amount'
            ]);
        });
    }
};
