<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // PK menggunakan UUID
            $table->uuid('id')->primary();

            // FK ke users (integer)
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();

            // FK ke addresses (integer)
            $table->foreignUuid('shipping_address_id')->constrained('addresses')->restrictOnDelete();

            $table->string('order_number', 20)->unique();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('shipping_cost', 10, 2);

            // Kolom Midtrans
            $table->string('midtrans_transaction_id')->unique()->nullable();
            $table->string('midtrans_snap_token')->nullable();

            // Status bisnis dan status pembayaran
            $table->enum('status', ['pending', 'processing', 'shipped', 'completed', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'expired', 'cancelled'])->default('unpaid');

            $table->string('payment_method', 50)->nullable();
            $table->timestamp('transaction_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
