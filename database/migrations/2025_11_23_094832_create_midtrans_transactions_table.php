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
        Schema::create('midtrans_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK ke orders (UUID)
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('midtrans_transaction_id')->unique();
            $table->string('status_code', 10);
            $table->string('transaction_status', 50);
            $table->string('payment_type', 50);
            $table->string('va_number', 50)->nullable();

            // Menyimpan respons lengkap Midtrans
            $table->json('json_data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('midtrans_transactions');
    }
};
