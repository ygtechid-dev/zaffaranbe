<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchPaymentConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('branch_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');

            // Payment Gateway Configuration
            $table->enum('payment_gateway', ['midtrans', 'xendit', 'manual'])->default('manual');

            // Midtrans Configuration
            $table->string('midtrans_server_key')->nullable();
            $table->string('midtrans_client_key')->nullable();
            $table->string('midtrans_merchant_id')->nullable();
            $table->boolean('midtrans_is_production')->default(false);

            // Xendit Configuration
            $table->string('xendit_api_key')->nullable();
            $table->string('xendit_callback_token')->nullable();
            $table->boolean('xendit_is_production')->default(false);

            // Payment Methods Enabled
            $table->json('enabled_payment_methods')->nullable(); // ['credit_card', 'bank_transfer', 'e_wallet', 'cash']

            // Bank Transfer Configuration
            $table->json('bank_accounts')->nullable(); // [{ bank_name, account_number, account_holder }]

            // E-Wallet Configuration
            $table->json('ewallet_accounts')->nullable(); // [{ provider, account_number }]

            // Payment Settings
            $table->decimal('minimum_payment', 15, 2)->default(0);
            $table->decimal('down_payment_percentage', 5, 2)->default(0); // 0-100%
            $table->boolean('allow_installment')->default(false);
            $table->integer('max_installment_months')->default(0);

            // Auto-confirmation
            $table->boolean('auto_confirm_payment')->default(false);
            $table->integer('payment_confirmation_timeout')->default(24); // hours

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure one config per branch
            $table->unique('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('branch_payment_configs');
    }
}
