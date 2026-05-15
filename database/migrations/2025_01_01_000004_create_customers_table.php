<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('whatsapp_phone')->nullable()->unique(); // E.164 e.g. +263771234567
            $table->enum('preferred_payment_method', ['ecocash', 'onemoney', 'card'])->nullable();
            $table->json('conversation_state')->nullable(); // WhatsApp bot state
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
