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
            $table->dropColumn([
                'subtotal_amount',
                'total_amount',
                'shipping_cost',
                'voucher_discount',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('total_amount');
            $table->bigInteger('shipping_cost');
            $table->bigInteger('voucher_discount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount',
                'total_amount',
                'shipping_cost',
                'voucher_discount',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('shipping_cost', 10, 2);
            $table->decimal('voucher_discount', 10, 2)->default(0);
        });
    }
};
