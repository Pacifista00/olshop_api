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
            // tambah kolom baru
            $table->boolean('points_deducted')
                ->default(false);

        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // rollback kolom baru
            $table->dropColumn('points_deducted');

        });
    }
};
