<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Change payment_method to string to allow dynamic methods like 'doku', 'edc', etc.
            $table->string('payment_method')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Revert back to enum if needed (might lose data if methods outside enum were used)
            // Ideally we shouldn't revert strictly to the old enum if we have new data
            // But for correctness of down():
            // $table->enum('payment_method', ['cash', 'qris', 'virtual_account', 'bank_transfer'])->change();
        });
    }
};
