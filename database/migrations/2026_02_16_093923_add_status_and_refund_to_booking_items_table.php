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
        Schema::table('booking_items', function (Blueprint $table) {
            $table->string('status')->default('active')->after('room_id'); // active, cancelled, refunded
            $table->decimal('refund_amount', 15, 2)->default(0)->after('price');
            $table->text('cancellation_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'refund_amount', 'cancellation_reason']);
        });
    }
};
