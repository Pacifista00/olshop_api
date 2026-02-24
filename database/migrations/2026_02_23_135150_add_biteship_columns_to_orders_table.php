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
            $table->string('biteship_order_id')->nullable()->after('id'); // Sesuaikan 'after' dengan kolom yang ada
            $table->string('tracking_number')->nullable()->after('biteship_order_id');
            $table->timestamp('shipment_created_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['biteship_order_id', 'tracking_number', 'shipment_created_at']);
        });
    }
};
