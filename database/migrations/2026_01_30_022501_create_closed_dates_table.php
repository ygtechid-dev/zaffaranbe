<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClosedDatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('closed_dates', function (Blueprint $image) {
            $image->id();
            $image->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $image->string('name');
            $image->date('start_date');
            $image->date('end_date')->nullable();
            $image->text('reason')->nullable();
            $image->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('closed_dates');
    }
}
