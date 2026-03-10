<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banner_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('banner_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->enum('type', ['view', 'click']);
            $table->timestamps();

            $table->foreign('banner_id')->references('id')->on('banners')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Allow unique constraint to prevent double counting per user/ip per day or just per banner
            // For now, let's keep it simple: one record per user-banner-type combination
            $table->unique(['banner_id', 'user_id', 'type'], 'banner_user_interaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_interactions');
    }
};
