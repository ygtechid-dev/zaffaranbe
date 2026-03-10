<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Category can be a string now to be more flexible (as user asked for specific ones but hinted they can be added)
        Schema::table('assets', function (Blueprint $table) {
            $table->string('category')->change();
            $table->string('condition')->change();
            $table->string('status')->change();
            $table->decimal('purchase_price', 15, 2)->change();
        });

        // Update existing data to match new format if possible
        DB::table('assets')->where('condition', 'baik')->update(['condition' => 'Baik']);
        DB::table('assets')->where('condition', 'perlu_perbaikan')->update(['condition' => 'Perlu Perbaikan']);
        DB::table('assets')->where('condition', 'rusak')->update(['condition' => 'Rusak']);

        DB::table('assets')->where('status', 'active')->update(['status' => 'Aktif']);
        DB::table('assets')->where('status', 'maintenance')->update(['status' => 'Nonaktif']);
        DB::table('assets')->where('status', 'disposed')->update(['status' => 'Nonaktif']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to rollback perfectly without knowing original values for Nonaktif
    }
};
