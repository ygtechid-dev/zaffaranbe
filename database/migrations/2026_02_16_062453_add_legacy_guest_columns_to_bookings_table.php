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
        Schema::table('bookings', function (Blueprint $table) {
            for ($i = 2; $i <= 5; $i++) {
                if (!Schema::hasColumn('bookings', "guest{$i}_name")) {
                    $table->string("guest{$i}_name")->nullable();
                }
                if (!Schema::hasColumn('bookings', "guest{$i}_whatsapp")) {
                    $table->string("guest{$i}_whatsapp")->nullable();
                }
                if (!Schema::hasColumn('bookings', "guest{$i}_age_type")) {
                    $table->string("guest{$i}_age_type")->nullable();
                }
                if (!Schema::hasColumn('bookings', "guest{$i}_age")) {
                    $table->string("guest{$i}_age")->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $cols = [];
            for ($i = 2; $i <= 5; $i++) {
                $cols[] = "guest{$i}_name";
                $cols[] = "guest{$i}_whatsapp";
                $cols[] = "guest{$i}_age_type";
                $cols[] = "guest{$i}_age";
            }
            $table->dropColumn($cols);
        });
    }
};
