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
        Schema::table('banners', function (Blueprint $table) {
            // Rename columns to match frontend expectations
            if (Schema::hasColumn('banners', 'image')) {
                $table->renameColumn('image', 'image_url');
            }
            if (Schema::hasColumn('banners', 'link')) {
                $table->renameColumn('link', 'link_url');
            }
            if (Schema::hasColumn('banners', 'order')) {
                $table->renameColumn('order', 'position');
            }

            // Add new columns
            if (!Schema::hasColumn('banners', 'link_type')) {
                $table->string('link_type')->default('none'); // none, internal, external
            }
            // branch_id already handled by previous migration
            if (!Schema::hasColumn('banners', 'views')) {
                $table->integer('views')->default(0);
            }
            if (!Schema::hasColumn('banners', 'clicks')) {
                $table->integer('clicks')->default(0);
            }

            // Modify existing columns
            // Make original 'type' (promo/news) nullable as frontend doesn't use it
            $table->string('type')->nullable()->change();

            // Change date to datetime for better precision with time inputs
            $table->dateTime('start_date')->nullable()->change();
            $table->dateTime('end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'image_url')) {
                $table->renameColumn('image_url', 'image');
            }
            if (Schema::hasColumn('banners', 'link_url')) {
                $table->renameColumn('link_url', 'link');
            }
            if (Schema::hasColumn('banners', 'position')) {
                $table->renameColumn('position', 'order');
            }

            if (Schema::hasColumn('banners', 'link_type')) {
                $table->dropColumn('link_type');
            }
            if (Schema::hasColumn('banners', 'views')) {
                $table->dropColumn('views');
            }
            if (Schema::hasColumn('banners', 'clicks')) {
                $table->dropColumn('clicks');
            }
        });
    }
};
