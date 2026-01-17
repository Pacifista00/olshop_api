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
        // ENUM → STRING (AMAN)
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
            $table->string('payment_status', 30)->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        // ROLLBACK ke ENUM (optional, tapi kita sediakan)
        DB::statement("
            ALTER TABLE orders
            MODIFY status ENUM(
                'pending',
                'processing',
                'shipped',
                'completed',
                'cancelled'
            ) DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE orders
            MODIFY payment_status ENUM(
                'unpaid',
                'pending',
                'paid',
                'failed',
                'expired',
                'cancelled'
            ) DEFAULT 'unpaid'
        ");
    }
};
