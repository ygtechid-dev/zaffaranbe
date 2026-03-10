<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('service_charge_amount', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('service_charge_amount');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('service_charge', 10, 2)->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['service_charge_amount', 'tax_amount']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('service_charge');
        });
    }
};
