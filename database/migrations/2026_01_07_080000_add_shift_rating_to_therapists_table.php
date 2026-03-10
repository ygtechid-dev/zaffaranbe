<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->string('shift')->default('Pagi (08:00-16:00)')->after('specialization');
            $table->decimal('rating', 2, 1)->nullable()->after('shift'); // e.g., 4.5, 3.8
        });
    }

    public function down(): void
    {
        Schema::table('therapists', function (Blueprint $table) {
            $table->dropColumn(['shift', 'rating']);
        });
    }
};
