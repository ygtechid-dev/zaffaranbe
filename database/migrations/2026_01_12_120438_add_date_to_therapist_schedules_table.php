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
        Schema::table('therapist_schedules', function (Blueprint $table) {
            $table->date('date')->nullable()->after('therapist_id');
            // Change day_of_week to be nullable
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('therapist_schedules', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable(false)->change();
        });
    }
};
