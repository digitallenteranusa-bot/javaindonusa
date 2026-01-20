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
        // First, update existing records from 'collector' to 'penagih'
        DB::table('users')->where('role', 'collector')->update(['role' => 'penagih']);

        // Then modify the enum to use 'penagih' instead of 'collector'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'penagih', 'technician', 'finance') DEFAULT 'penagih'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: update records from 'penagih' back to 'collector'
        DB::table('users')->where('role', 'penagih')->update(['role' => 'collector']);

        // Revert enum back to original
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'collector', 'technician', 'finance') DEFAULT 'collector'");
    }
};
