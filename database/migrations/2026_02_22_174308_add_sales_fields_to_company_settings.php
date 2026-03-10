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
            if (!Schema::hasColumn('company_settings', 'assistant_commission')) {
                $table->string('assistant_commission')->default('Bagi rata');
            }
            if (!Schema::hasColumn('company_settings', 'voucher_expiration')) {
                $table->string('voucher_expiration')->default('1 month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'assistant_commission')) {
                $table->dropColumn('assistant_commission');
            }
            if (Schema::hasColumn('company_settings', 'voucher_expiration')) {
                $table->dropColumn('voucher_expiration');
            }
        });
    }
};
