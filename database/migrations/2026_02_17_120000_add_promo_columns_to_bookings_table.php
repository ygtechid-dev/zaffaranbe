<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('total_price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'discount_amount']);
        });
    }
};
