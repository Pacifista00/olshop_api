<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('type', ['voucher', 'product', 'hotel']);

            $table->integer('points_required');

            $table->integer('stock')->nullable();
            $table->integer('redeemed_count')->default(0);

            // ======================
            // VOUCHER
            // ======================
            $table->enum('voucher_type', ['percentage', 'fixed'])->nullable();
            $table->unsignedBigInteger('voucher_value')->nullable();
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();

            // ======================
            // PRODUCT
            // ======================
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('product_price')->nullable();
            $table->boolean('need_shipping')->default(false);

            // ======================
            // HOTEL
            // ======================
            $table->string('hotel_name')->nullable();
            $table->string('room_type', 100)->nullable();
            $table->string('location')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
