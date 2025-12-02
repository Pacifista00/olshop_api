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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK ke carts (integer ID)
            $table->foreignUuid('cart_id')->constrained('carts')->cascadeOnDelete();

            // FK ke products (UUID)
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // Kombo unik: satu produk hanya sekali dalam satu keranjang
            $table->unique(['cart_id', 'product_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
