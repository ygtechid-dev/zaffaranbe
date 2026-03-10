<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('password');
            $table->string('registration_source')->default('walk-in')->after('branch_id');
            $table->string('membership_status')->default('new')->after('registration_source');
            $table->boolean('has_app_account')->default(false)->after('membership_status');
            $table->text('notes')->nullable()->after('has_app_account');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'registration_source', 'membership_status', 'has_app_account', 'notes']);
        });
    }
};
