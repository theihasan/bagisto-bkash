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
        Schema::create('bkash_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id');
            $table->string('token', 255);
            $table->decimal('amount', 12, 2);
            $table->string('invoice_number');
            $table->string('status')->default(\Ihasan\Bkash\PaymentStatus::PENDING->value);
            $table->foreignId('cart_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bkash_payments');
    }
};