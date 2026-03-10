<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_agenda_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_item_id')->nullable()->constrained('booking_items')->onDelete('set null');
            $table->string('action'); // 'reschedule', 'cancel_item', 'update_service', 'add_item'
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->decimal('price_difference', 15, 2)->default(0); // new_price - old_price
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_agenda_logs');
    }
};
