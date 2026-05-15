<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // ORD-2025-XXXX
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('channel', ['whatsapp', 'web', 'app'])->default('whatsapp');
            $table->enum('status', [
                'pending_payment',
                'paid',
                'preparing',
                'assigned',
                'in_transit',
                'delivered',
                'cancelled',
            ])->default('pending_payment');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('payment_method', ['ecocash', 'onemoney', 'card'])->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('paynow_poll_url')->nullable();
            $table->string('paynow_reference')->nullable();
            $table->string('ecocash_number')->nullable();
            $table->foreignId('delivery_address_id')->nullable()->constrained('delivery_addresses')->nullOnDelete();
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_token')->unique()->nullable(); // UUID for public tracking
            $table->text('notes')->nullable();
            $table->string('whatsapp_order_id')->nullable(); // from WhatsApp webhook
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
