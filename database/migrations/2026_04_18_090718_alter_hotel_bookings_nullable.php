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
        Schema::table('hotel_bookings', function (Blueprint $table) {
            // 🔥 hapus unique dulu (kalau ada)
            $table->dropUnique(['booking_code']);

            // 🔥 drop kolom lama
            $table->dropColumn('booking_code');
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            // 🔥 buat ulang nullable + unique
            $table->string('booking_code')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropUnique(['booking_code']);
            $table->dropColumn('booking_code');
        });

        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->string('booking_code')->nullable(false)->unique();
        });
    }
};
