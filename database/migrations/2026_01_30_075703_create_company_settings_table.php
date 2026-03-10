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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('business_type')->nullable(); // Spa, Salon, etc.
            
            // Contact & Location
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();

            // Regional Settings
            $table->string('timezone')->default('WIB'); // WIB, WITA, WIT
            $table->string('time_format')->default('24h'); // 12h, 24h
            $table->string('country')->default('Indonesia');
            $table->string('currency')->default('Rupiah');

            // Operation
            $table->json('facilities')->nullable();
            $table->json('operating_days')->nullable(); // Added operating days
            $table->time('default_open_time')->default('09:00:00');
            $table->time('default_close_time')->default('21:00:00');

            // Payment & Tax
            $table->double('tax_percentage')->default(0);
            $table->double('service_charge_percentage')->default(0);

            $table->timestamps();

            // Foreign key constraint if branches table exists
             // $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
