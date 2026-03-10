<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $tables = ['users', 'therapists', 'services', 'rooms', 'bookings'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                // First, permanently remove any soft-deleted records
                DB::table($table)->whereNotNull('deleted_at')->delete();

                // Then drop the deleted_at column
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['users', 'therapists', 'services', 'rooms', 'bookings'];

        foreach ($tables as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }
};
