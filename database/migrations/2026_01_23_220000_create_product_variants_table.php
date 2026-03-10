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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Besar 500 ml", "Kecil 200 ml"
            $table->string('sku')->unique();
            $table->decimal('retail_price', 15, 2)->default(0);
            $table->decimal('special_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create variant stocks table
        Schema::create('product_variant_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('location')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_stocks');
        Schema::dropIfExists('product_variants');
    }
};
