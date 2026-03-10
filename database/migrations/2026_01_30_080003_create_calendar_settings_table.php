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
        Schema::create('calendar_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->integer('start_hour')->default(9);
            $table->integer('end_hour')->default(21);
            $table->integer('slot_duration')->default(15);
            $table->string('default_view')->default('day'); // day, week, month
            $table->string('agenda_color')->default('staff'); // staff, status, service_group
            $table->string('week_start')->default('sunday'); // sunday, monday, etc.
            $table->string('staff_order')->default('default'); // default, name
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_settings');
    }
};
