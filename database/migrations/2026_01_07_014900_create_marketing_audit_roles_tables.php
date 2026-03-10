<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Promos/Vouchers table
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['percent', 'nominal'])->default('percent');
            $table->decimal('discount', 10, 2);
            $table->string('code')->unique();
            $table->integer('quota')->default(100);
            $table->integer('used')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'disabled'])->default('active');
            $table->text('description')->nullable();
            $table->decimal('min_purchase', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->json('applicable_services')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // News/Articles table
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('News');
            $table->enum('status', ['published', 'draft'])->default('draft');
            $table->text('content')->nullable();
            $table->string('image_url')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('views')->default(0);
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users')->onDelete('set null');
        });

        // Campaigns table
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('promo'); // birthday, winback, feedback, promo, reminder
            $table->text('target_audience')->nullable();
            $table->text('message')->nullable();
            $table->integer('sent')->default(0);
            $table->integer('opened')->default(0);
            $table->integer('converted')->default(0);
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Automation Rules table
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('trigger', ['birthday', 'winback', 'post_visit', 'anniversary', 'first_visit'])->default('birthday');
            $table->boolean('is_active')->default(true);
            $table->integer('days_offset')->default(0);
            $table->enum('channel', ['whatsapp', 'email', 'sms'])->default('whatsapp');
            $table->text('message');
            $table->string('discount_code')->nullable();
            $table->timestamp('last_triggered')->nullable();
            $table->integer('total_sent')->default(0);
            $table->timestamps();
        });

        // Audit Logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name');
            $table->string('user_role')->nullable();
            $table->enum('action', ['create', 'update', 'delete', 'login', 'logout', 'view', 'export', 'settings'])->default('view');
            $table->string('module');
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'action']);
        });

        // Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // User-Role pivot (if needed for multiple roles per user)
        // For now, users have single role in 'role' column
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('news');
        Schema::dropIfExists('promos');
    }
};
