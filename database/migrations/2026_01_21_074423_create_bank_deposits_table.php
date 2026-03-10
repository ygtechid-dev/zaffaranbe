<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users');
            $table->decimal('amount', 12, 2); // Jumlah yang disetor
            $table->decimal('cash_before', 12, 2); // Saldo cash sebelum setor
            $table->decimal('cash_after', 12, 2); // Saldo cash setelah setor
            $table->string('bank_name'); // Nama bank tujuan
            $table->string('account_number')->nullable(); // Nomor rekening
            $table->text('deposit_proof')->nullable(); // Bukti setor (image/pdf)
            $table->text('notes')->nullable(); // Catatan
            $table->date('deposit_date'); // Tanggal setor
            $table->timestamps();
        });

        // Tabel untuk tracking saldo cash per cabang
        Schema::create('cash_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_deposits');
        Schema::dropIfExists('cash_balances');
    }
};
