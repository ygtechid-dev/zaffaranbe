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
            // Ubah tipe kolom category dari enum ke string agar fleksibel
            $table->string('category')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Kembalikan ke enum jika rollback (Opsi, tapi riskan data hilang jika ada kategori baru)
            // Sebaiknya biarkan string atau definisikan enum ulang dengan hati-hati
            $table->enum('category', ['Massage', 'Body Treatment', 'Face Treatment', 'Hair Treatment', 'Packages'])->change();
        });
    }
};
