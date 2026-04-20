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
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->foreignUuid('voucher_id')
                ->nullable()
                ->after('reward_id')
                ->constrained('vouchers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });
    }
};
