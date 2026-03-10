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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['furniture', 'equipment', 'electronic', 'other'])->default('other');
            $table->string('location');
            $table->date('purchase_date');
            $table->decimal('purchase_price', 15, 2);
            $table->enum('condition', ['baik', 'perlu_perbaikan', 'rusak'])->default('baik');
            $table->date('last_maintenance')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'maintenance', 'disposed'])->default('active');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
