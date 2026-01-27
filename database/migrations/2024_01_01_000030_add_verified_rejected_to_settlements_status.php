<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support easy ENUM modification, so we use raw SQL
        DB::statement("ALTER TABLE settlements MODIFY COLUMN status ENUM('pending', 'settled', 'discrepancy', 'verified', 'rejected') DEFAULT 'pending'");

        // Add verification_notes column if not exists
        if (!Schema::hasColumn('settlements', 'verification_notes')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->text('verification_notes')->nullable()->after('notes');
            });
        }

        // Add verified_by column if not exists
        if (!Schema::hasColumn('settlements', 'verified_by')) {
            Schema::table('settlements', function (Blueprint $table) {
                $table->foreignId('verified_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert verified/rejected back to pending before removing enum values
        DB::statement("UPDATE settlements SET status = 'pending' WHERE status IN ('verified', 'rejected')");
        DB::statement("ALTER TABLE settlements MODIFY COLUMN status ENUM('pending', 'settled', 'discrepancy') DEFAULT 'pending'");

        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['verification_notes']);
        });
    }
};
