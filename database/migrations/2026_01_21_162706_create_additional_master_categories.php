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
        // 1. Asset Categories
        if (!Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_global')->default(true);
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->timestamps();
            });
        }

        // 2. Service Categories
        if (!Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_global')->default(true);
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->timestamps();
            });
        }

        // 3. Update news_categories (Artikel)
        if (Schema::hasTable('news_categories')) {
            Schema::table('news_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('news_categories', 'is_global')) {
                    $table->boolean('is_global')->default(true)->after('name');
                }
                if (!Schema::hasColumn('news_categories', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->after('is_global');
                }
            });
        } else {
            Schema::create('news_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_global')->default(true);
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->timestamps();
            });
        }

        // 4. Pivot Tables for multi-branch support
        $newTables = [
            'asset_categories' => 'asset_category',
            'service_categories' => 'service_category',
            'news_categories' => 'news_category'
        ];

        foreach ($newTables as $tableName => $singular) {
            $pivotName = $singular . '_branch';
            if (!Schema::hasTable($pivotName)) {
                Schema::create($pivotName, function (Blueprint $table) use ($singular, $tableName) {
                    $table->id();
                    $table->foreignId($singular . '_id')->constrained($tableName)->cascadeOnDelete();
                    $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                    $table->timestamps();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_category_branch');
        Schema::dropIfExists('service_category_branch');
        Schema::dropIfExists('news_category_branch');
        
        // We might not want to drop the main tables if they existed before, 
        // but for asset and service categories we just created them.
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('service_categories');
        
        if (Schema::hasTable('news_categories')) {
            Schema::table('news_categories', function (Blueprint $table) {
                $table->dropColumn(['is_global', 'branch_id']);
            });
        }
    }
};
