<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('suburb_keywords'); // array of suburb names
            $table->decimal('base_fee', 8, 2)->default(0);
            $table->decimal('per_km_fee', 8, 2)->default(0);
            $table->decimal('free_delivery_above', 10, 2)->nullable(); // order value threshold
            $table->unsignedInteger('estimated_minutes')->default(60);
            $table->decimal('hub_lat', 10, 7)->nullable();
            $table->decimal('hub_lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
