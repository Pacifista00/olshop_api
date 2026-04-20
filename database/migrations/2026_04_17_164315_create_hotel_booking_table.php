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
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('reward_id')
                ->constrained('rewards')
                ->cascadeOnDelete();

            $table->foreignUuid('reward_redemption_id')
                ->constrained('reward_redemptions')
                ->cascadeOnDelete();

            $table->string('hotel_name');
            $table->string('room_type');
            $table->string('location');

            $table->date('check_in');
            $table->date('check_out');

            $table->string('booking_code')->unique(); // kode booking untuk user
            $table->string('status')->default('booked'); // booked, cancelled, used

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['reward_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
