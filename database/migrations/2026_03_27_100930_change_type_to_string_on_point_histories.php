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
        Schema::table('point_histories', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE point_histories 
            MODIFY COLUMN type ENUM('earn', 'spend') NOT NULL
        ");
    }
};
