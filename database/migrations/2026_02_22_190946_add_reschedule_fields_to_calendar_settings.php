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
        Schema::table('calendar_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_settings', 'allow_reschedule')) {
                $table->boolean('allow_reschedule')->default(true);
            }
            if (!Schema::hasColumn('calendar_settings', 'reschedule_deadline')) {
                $table->integer('reschedule_deadline')->default(24);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_settings', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_settings', 'allow_reschedule')) {
                $table->dropColumn('allow_reschedule');
            }
            if (Schema::hasColumn('calendar_settings', 'reschedule_deadline')) {
                $table->dropColumn('reschedule_deadline');
            }
        });
    }
};
