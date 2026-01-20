<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan foreign key yang tidak bisa ditambahkan saat create table
     * karena urutan pembuatan tabel (circular dependencies)
     */
    public function up(): void
    {
        // Add foreign key from users to areas
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('area_id')
                ->references('id')
                ->on('areas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });
    }
};
