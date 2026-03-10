<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Staff Attendances (Jam Kerja)
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->dateTime('check_in');
            $table->dateTime('check_out')->nullable();
            $table->string('status')->default('present'); // present, late, absent, leave
            $table->text('notes')->nullable();
            $table->timestamps();

            // $table->foreign('staff_id')->references('id')->on('users');
            // $table->foreign('branch_id')->references('id')->on('branches');
        });

        // 2. Staff Commissions (Komisi)
        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('transaction_id')->nullable(); // Link to Transaction
            $table->unsignedBigInteger('booking_id')->nullable(); // Link to Booking (Service)
            $table->unsignedBigInteger('item_id')->nullable(); // Product/Item ID if applicable
            $table->string('item_type')->nullable(); // service, product, voucher, class, plan_class
            $table->string('item_name')->nullable(); // Snapshot of item name
            $table->string('item_variant_name')->nullable(); // Snapshot of variant
            $table->decimal('sales_amount', 15, 2)->default(0); // Harga jual item
            $table->integer('qty')->default(1);
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->date('payment_date')->nullable(); // Kapan komisi dibayarkan ke staff (atau tanggal invoice lunas)
            $table->string('status')->default('pending'); // pending, paid
            $table->timestamps();

            // $table->foreign('staff_id')->references('id')->on('users');
        });

        // 3. Staff Tips (Tip)
        Schema::create('staff_tips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('amount_collected', 15, 2)->default(0); // Tip dari customer (via kasir)
            $table->decimal('amount_returned', 15, 2)->default(0); // Tip yang sudah diserahkan ke staff
            $table->date('date');
            $table->timestamps();

            // $table->foreign('staff_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_tips');
        Schema::dropIfExists('staff_commissions');
        Schema::dropIfExists('staff_attendances');
    }
};
