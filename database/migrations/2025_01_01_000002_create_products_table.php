<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->decimal('price_zwl', 10, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->string('unit')->default('piece'); // kg, bunch, piece
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->string('whatsapp_retailer_id')->nullable(); // Meta catalog product ID
            $table->enum('whatsapp_sync_status', ['pending', 'synced', 'failed', 'not_synced'])->default('not_synced');
            $table->timestamp('whatsapp_synced_at')->nullable();
            $table->text('whatsapp_sync_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_whatsapp_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
