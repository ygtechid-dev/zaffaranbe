<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDownPaymentAmountToBranchPaymentConfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('branch_payment_configs', function (Blueprint $table) {
            $table->decimal('down_payment_amount', 15, 2)->default(0)->after('down_payment_percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('branch_payment_configs', function (Blueprint $table) {
            $table->dropColumn('down_payment_amount');
        });
    }
}
