<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('payment_ref')->unique();
            $table->enum('payment_type', ['down_payment', 'full_payment', 'remaining_payment']);
            $table->enum('payment_method', ['cash', 'edc', 'qris', 'virtual_account', 'bank_transfer']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');
            $table->string('external_id')->nullable(); // Payment Gateway Transaction ID
            $table->text('payment_data')->nullable(); // JSON for payment gateway response
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
