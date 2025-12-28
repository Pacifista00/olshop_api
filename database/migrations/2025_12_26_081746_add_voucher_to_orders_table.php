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
        Schema::table('orders', function (Blueprint $table) {

            // FK voucher
            $table->foreignUuid('voucher_id')
                ->nullable()
                ->after('shipping_cost')
                ->constrained('vouchers')
                ->nullOnDelete();

            // Snapshot nilai diskon
            $table->decimal('voucher_discount', 10, 2)
                ->default(0)
                ->after('voucher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {

            // Drop FK dulu
            $table->dropForeign(['voucher_id']);

            // Drop kolom
            $table->dropColumn(['voucher_id', 'voucher_discount']);
        });
    }
};
