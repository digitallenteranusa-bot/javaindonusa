<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove the unique constraint on code to allow soft-deleted records
     * to have the same code as new records.
     * Uniqueness is now handled at application level with whereNull('deleted_at')
     */
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique(['code']);
            // Add regular index for performance (not unique)
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });
    }
};
