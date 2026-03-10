<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            // Assuming transactions table exists. Note: earlier codebase showed Transaction model.
            // If strictly enforced foreign key fails because seeders run out of order or circular dependency, allow nullable.
            // But here we assume transaction created first.
            $table->unsignedBigInteger('transaction_id')->nullable(); 
            $table->integer('points')->default(0); // Points earned
            $table->integer('remaining_points')->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('point_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('transaction_id')->nullable(); // Invoice usage
            $table->integer('points_used');
            $table->enum('type', ['item', 'discount']);
            $table->string('item_name')->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_redemptions');
        Schema::dropIfExists('loyalty_points');
    }
};
