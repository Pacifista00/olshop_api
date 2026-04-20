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
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reward_id')->constrained('rewards')->cascadeOnDelete();

            $table->integer('points_used');

            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');

            $table->string('phone', 20)->nullable();
            $table->json('details')->nullable();

            $table->string('reference_code')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('reward_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
