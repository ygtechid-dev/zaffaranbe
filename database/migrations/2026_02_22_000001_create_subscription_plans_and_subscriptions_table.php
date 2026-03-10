<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Table for plan configurations (managed by super admin)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key')->unique(); // e.g., 'starter', 'pro'
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_monthly')->default(0);
            $table->unsignedInteger('price_yearly')->default(0);
            $table->json('features')->nullable(); // list of feature keys/names included
            $table->json('menu_permissions')->nullable(); // list of menu permission keys allowed
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Table for active subscriptions per branch
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('plan_key')->default('starter'); // 'starter' or 'pro'
            $table->string('interval')->default('month'); // 'month', 'year', 'lifetime'
            $table->string('status')->default('active'); // 'active', 'expired', 'pending', 'cancelled'
            $table->string('payment_method')->nullable(); // 'qris', 'bank_transfer', etc.
            $table->string('payment_ref')->nullable();
            $table->unsignedInteger('amount')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
