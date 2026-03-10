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
        Schema::table('therapist_commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('therapist_commissions', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('service_id')->constrained()->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapist_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('therapist_commissions', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
        });
    }
};
