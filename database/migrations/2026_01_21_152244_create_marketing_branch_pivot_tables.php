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
            'news',
            'campaigns',
            'automation_rules',
            'banners',
            'services'
        ];

        foreach ($tables as $tableName) {
            // Add is_global column if not exists
            if (!Schema::hasColumn($tableName, 'is_global')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('is_global')->default(false)->after('id');
                });
            }

            // Create pivot table for marketing tables (services already has branch_service)
            if ($tableName !== 'services') {
                $singular = match($tableName) {
                    'news' => 'news', // 'news' is plural/singular enough but news_id is standard
                    'automation_rules' => 'automation_rule',
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
                }
            } else {
                // For services, if it's not in branch_service, it's global? 
                // Actually, let's keep services logic as is or set is_global=true if no branches.
                $items = DB::table('services')->get();
                foreach ($items as $item) {
                    $hasBranches = DB::table('branch_service')->where('service_id', $item->id)->exists();
                    if (!$hasBranches) {
                        DB::table('services')->where('id', $item->id)->update(['is_global' => true]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'news',
            'campaigns',
            'automation_rules',
            'banners',
            'services'
        ];

        foreach ($tables as $tableName) {
            if ($tableName !== 'services') {
                $singular = match($tableName) {
                    'news' => 'news',
                    'automation_rules' => 'automation_rule',
                    default => preg_replace('/s$/', '', $tableName),
                };
                $pivotName = $singular . '_branch';

                Schema::dropIfExists($pivotName);
            }
            
            if (Schema::hasColumn($tableName, 'is_global')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('is_global');
                });
            }
        }
    }
};
