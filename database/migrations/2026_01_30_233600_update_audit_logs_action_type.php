<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change action column from ENUM to VARCHAR to allow flexible action names
        // Since doctrine/dbal is not installed, we use raw SQL
        // MySQL syntax:
        DB::statement("ALTER TABLE audit_logs MODIFY COLUMN action VARCHAR(50) NOT NULL DEFAULT 'view'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to ENUM (if possible, data might be truncated if non-enum values exist)
        // We will just leave it as VARCHAR or attempt to revert if needed.
        // But reverting to restricted ENUM is dangerous if we have new values.
        // For safety, we can revert to VARCHAR with same length.
        // But if we truly want to revert to original state:
        // DB::statement("ALTER TABLE audit_logs MODIFY COLUMN action ENUM('create', 'update', 'delete', 'login', 'logout', 'view', 'export', 'settings') DEFAULT 'view'");
    }
};
