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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FK ke orders (UUID)
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            // FK ke products (UUID)
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);

            $table->unique(['order_id', 'product_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
