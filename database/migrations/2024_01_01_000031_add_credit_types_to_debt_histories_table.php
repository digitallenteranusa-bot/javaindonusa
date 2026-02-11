<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE debt_histories MODIFY COLUMN type ENUM('charge', 'payment', 'adjustment_add', 'adjustment_subtract', 'discount', 'late_fee', 'writeoff', 'credit_added', 'credit_used') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE debt_histories MODIFY COLUMN type ENUM('charge', 'payment', 'adjustment_add', 'adjustment_subtract', 'discount', 'late_fee', 'writeoff') NOT NULL");
    }
};
