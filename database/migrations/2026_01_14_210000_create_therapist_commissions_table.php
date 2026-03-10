<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('therapist_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['service', 'product'])->default('service');
            $table->decimal('commission_rate', 10, 2)->default(0)->comment('Percentage or fixed amount');
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->boolean('is_default')->default(false)->comment('If true, applies to all services/products');
            $table->timestamps();

            // Unique constraint for therapist + service combination
            $table->unique(['therapist_id', 'service_id', 'type'], 'unique_therapist_service_commission');
        });

        // Add default commission columns to therapist if not exists
        Schema::table('therapists', function (Blueprint $table) {
            if (!Schema::hasColumn('therapists', 'default_service_commission')) {
                $table->decimal('default_service_commission', 10, 2)->default(0)->after('is_active');
                $table->decimal('default_product_commission', 10, 2)->default(0)->after('default_service_commission');
                $table->enum('commission_type', ['percent', 'fixed'])->default('percent')->after('default_product_commission');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_commissions');

        Schema::table('therapists', function (Blueprint $table) {
            if (Schema::hasColumn('therapists', 'default_service_commission')) {
                $table->dropColumn(['default_service_commission', 'default_product_commission', 'commission_type']);
            }
        });
    }
};
