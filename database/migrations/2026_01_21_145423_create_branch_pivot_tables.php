<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'brands',
            'categories',
            'suppliers',
            'rooms',
            'assets',
            'expense_categories',
            'payment_methods',
            'products'
        ];

        foreach ($tables as $tableName) {
            // Add is_global column if not exists
            if (!Schema::hasColumn($tableName, 'is_global')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('is_global')->default(false)->after('id');
                });
            }

            // Create pivot table
            // Handle singular names correctly
            $singular = match($tableName) {
                'payment_methods' => 'payment_method',
                'expense_categories' => 'expense_category',
                'categories' => 'category',
                'brands' => 'brand',
                'suppliers' => 'supplier',
                'rooms' => 'room',
                'assets' => 'asset',
                'products' => 'product',
                default => preg_replace('/s$/', '', $tableName),
            };
            
            $pivotName = $singular . '_branch';

            if (!Schema::hasTable($pivotName)) {
                Schema::create($pivotName, function (Blueprint $table) use ($singular, $tableName) {
                    $table->id();
                    $table->foreignId($singular . '_id')->constrained($tableName)->cascadeOnDelete();
                    $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                    $table->timestamps();
                });
            }

            // Migrate data
            if (Schema::hasColumn($tableName, 'branch_id')) {
                $items = DB::table($tableName)->get();
                foreach ($items as $item) {
                    if (is_null($item->branch_id)) {
                        DB::table($tableName)->where('id', $item->id)->update(['is_global' => true]);
                    } else {
                        // Check if pivot record exists
                        $exists = DB::table($pivotName)
                            ->where($singular . '_id', $item->id)
                            ->where('branch_id', $item->branch_id)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table($pivotName)->insert([
                                $singular . '_id' => $item->id,
                                'branch_id' => $item->branch_id,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
            } else {
                // If no branch_id, assume global for now (e.g. payment_methods)
                DB::table($tableName)->update(['is_global' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'brands',
            'categories',
            'suppliers',
            'rooms',
            'assets',
            'expense_categories',
            'payment_methods',
            'products'
        ];

        foreach ($tables as $tableName) {
            $singular = match($tableName) {
                'payment_methods' => 'payment_method',
                'expense_categories' => 'expense_category',
                'categories' => 'category',
                'brands' => 'brand',
                'suppliers' => 'supplier',
                'rooms' => 'room',
                'assets' => 'asset',
                'products' => 'product',
                default => preg_replace('/s$/', '', $tableName),
            };
            $pivotName = $singular . '_branch';

            Schema::dropIfExists($pivotName);
            
            if (Schema::hasColumn($tableName, 'is_global')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('is_global');
                });
            }
        }
    }
};
