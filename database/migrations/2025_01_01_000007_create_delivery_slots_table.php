<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('label'); // e.g. "Morning 7am–12pm"
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedInteger('max_orders')->default(20);
            $table->unsignedInteger('current_orders')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_slots');
    }
};
