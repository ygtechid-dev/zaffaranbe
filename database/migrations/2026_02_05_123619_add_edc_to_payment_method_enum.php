<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any existing invalid payment methods to 'cash' as a safe default
        // This prevents data truncation errors when altering the enum
        DB::statement("UPDATE payments SET payment_method = 'cash' WHERE payment_method NOT IN ('cash', 'qris', 'virtual_account', 'bank_transfer')");

        // Now safely alter the enum to include 'edc'
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash', 'edc', 'qris', 'virtual_account', 'bank_transfer')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update edc payments to cash before removing from enum
        DB::statement("UPDATE payments SET payment_method = 'cash' WHERE payment_method = 'edc'");

        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash', 'qris', 'virtual_account', 'bank_transfer')");
    }
};
