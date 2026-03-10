<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceSettingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('invoice_settings')) {
            Schema::create('invoice_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->boolean('auto_print')->default(true);
                $table->boolean('show_customer_name')->default(true);
                $table->boolean('show_customer_contact')->default(false);
                $table->boolean('show_customer_address')->default(false);
                $table->boolean('show_logo')->default(true);
                $table->boolean('show_item_details')->default(true);
                $table->boolean('show_item_price')->default(false);
                $table->boolean('show_staff_name')->default(true);
                $table->boolean('show_tax')->default(true);
                $table->boolean('hide_company_name')->default(false);
                $table->boolean('hide_cashier_name')->default(false);
                $table->boolean('apply_to_all_locations')->default(true);
                $table->json('social_media')->nullable();
                $table->text('custom_header')->nullable();
                $table->text('custom_footer')->nullable();
                $table->timestamps();

                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('invoice_settings');
    }
}
