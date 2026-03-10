<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTables extends Migration
{
    public function up()
    {
        // Cancellation Reasons
        if (!Schema::hasTable('cancellation_reasons')) {
            Schema::create('cancellation_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('reason');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            // Seed default reasons
            DB::table('cancellation_reasons')->insert([
                ['reason' => 'Appointment Made by Mistake', 'is_active' => true, 'sort_order' => 1, 'created_at' => \Carbon\Carbon::now(), 'updated_at' => \Carbon\Carbon::now()],
                ['reason' => 'Appointment invoice not paid', 'is_active' => true, 'sort_order' => 2, 'created_at' => \Carbon\Carbon::now(), 'updated_at' => \Carbon\Carbon::now()],
                ['reason' => 'Other', 'is_active' => true, 'sort_order' => 3, 'created_at' => \Carbon\Carbon::now(), 'updated_at' => \Carbon\Carbon::now()],
                ['reason' => 'Canceled because payment time is expired', 'is_active' => true, 'sort_order' => 4, 'created_at' => \Carbon\Carbon::now(), 'updated_at' => \Carbon\Carbon::now()],
                ['reason' => 'Rebooking', 'is_active' => true, 'sort_order' => 5, 'created_at' => \Carbon\Carbon::now(), 'updated_at' => \Carbon\Carbon::now()],
            ]);
        }

        // Bank Accounts
        if (!Schema::hasTable('bank_payment_configs')) {
            Schema::create('bank_payment_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('account_name');
                $table->string('branch')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            });
        }

        // Queue Settings
        if (!Schema::hasTable('queue_settings')) {
            Schema::create('queue_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('ongoing_bg_color')->default('#FF4500');
                $table->string('ongoing_text_color')->default('#000000');
                $table->string('max_services')->default('unlimited');
                $table->boolean('require_name_phone')->default(true);
                $table->boolean('queue_mode_enabled')->default(false);
                $table->timestamps();
            });
        }

        // Loyalty Settings
        if (!Schema::hasTable('loyalty_program_settings')) {
            Schema::create('loyalty_program_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->boolean('enabled')->default(true);
                $table->string('earning_type')->default('amount_spent'); // amount_spent | item_purchased
                $table->decimal('points_per_amount', 10, 2)->default(1);
                $table->decimal('min_order_amount', 15, 2)->default(50000);
                $table->string('expiration')->default('After 1 Year');
                $table->boolean('apply_multiples')->default(true);
                $table->boolean('earn_when_redeeming')->default(true);
                $table->json('channels')->nullable();
                $table->json('customer_groups')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('loyalty_program_settings');
        Schema::dropIfExists('queue_settings');
        Schema::dropIfExists('bank_payment_configs');
        Schema::dropIfExists('cancellation_reasons');
    }
}
