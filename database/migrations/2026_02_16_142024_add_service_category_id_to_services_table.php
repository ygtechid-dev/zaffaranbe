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
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_category_id')->nullable()->after('category');
            
            // Note: We don't enforce foreign key constraint yet to allow flexible seeding, 
            // or if categories are deleted. But ideal is constrained().
            // $table->foreign('service_category_id')->references('id')->on('service_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('service_category_id');
        });
    }
};
