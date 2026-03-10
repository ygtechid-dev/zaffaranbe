<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('salon_name');
            $table->string('pic_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->enum('status', [
                'new',
                'contacted',
                'demo_scheduled',
                'negotiation',
                'converted',
                'lost',
            ])->default('new');
            $table->text('notes')->nullable();
            $table->string('source')->default('landing_page');
            $table->timestamps();

            $table->index('status');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
