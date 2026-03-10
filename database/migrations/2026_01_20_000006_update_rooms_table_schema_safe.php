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
        Schema::table('rooms', function (Blueprint $table) {
            // Check if columns exist before modifying or adding
            if (Schema::hasColumn('rooms', 'code')) {
                $table->string('code')->nullable()->change();
            }
            // If 'type' column doesn't exist, we can create it or skip modifying it. 
            // Assuming it might have been removed or renamed in a previous migration not seen.
            // Let's ensure 'type' exists if we need it, or just ignore if not used by frontend anymore.
            // Frontend doesn't seem to use 'type' explicitly in form, but controller reference it. 
            // Let's add 'type' as nullable if not exists, or modify if exists.
            if (!Schema::hasColumn('rooms', 'type')) {
                $table->string('type')->nullable()->after('code');
            } else {
                $table->string('type')->nullable()->change();
            }

            if (!Schema::hasColumn('rooms', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('rooms', 'capacity')) {
                $table->integer('capacity')->default(1)->after('name');
            }
            if (!Schema::hasColumn('rooms', 'quantity')) {
                $table->integer('quantity')->default(1)->after('capacity');
            }
            if (!Schema::hasColumn('rooms', 'status')) {
                $table->string('status')->default('available')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Reverse operations safely?
            // It's tricky to reverse exactly without knowing initial state perfectly.
            // We can drop added columns.
            $table->dropColumn(['description', 'capacity', 'quantity', 'status']);
        });
    }
};
