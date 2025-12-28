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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 30)->unique(); // KODE VOUCHER
            $table->string('name')->nullable();

            // Jenis diskon
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2); // % atau nominal

            // Batasan
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_order_amount', 10, 2)->nullable();

            // Kuota
            $table->integer('usage_limit')->nullable(); // total pemakaian
            $table->integer('usage_count')->default(0);

            // Masa berlaku
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
