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
        Schema::create('redeemed_products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->foreignUuid('reward_redemption_id')
                ->constrained('reward_redemptions')
                ->cascadeOnDelete();

            $table->string('product_name');
            $table->text('address');
            $table->string('recipient_name');
            $table->string('phone')->nullable();
            $table->string('tracking_number')->nullable();

            $table->string('status')->default('processing');

            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('reward_redemption_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redeemed_products');
    }
};
