<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Change payment_method from ENUM to VARCHAR to support dynamic payment methods
        DB::statement("ALTER TABLE transactions MODIFY payment_method VARCHAR(50)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash', 'qris', 'virtual_account', 'bank_transfer')");
    }
};
