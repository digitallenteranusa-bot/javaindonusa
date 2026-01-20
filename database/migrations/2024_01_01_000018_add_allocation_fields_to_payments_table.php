<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add received_by field (admin/user who received the payment)
            $table->foreignId('received_by')->nullable()->after('collector_id')
                ->constrained('users')->nullOnDelete();

            // Add allocation tracking fields
            $table->decimal('allocated_to_invoice', 14, 2)->default(0)->after('allocated_invoices')
                ->comment('Total amount allocated to invoices');
            $table->decimal('allocated_to_debt', 14, 2)->default(0)->after('allocated_to_invoice')
                ->comment('Amount for general debt reduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropColumn(['received_by', 'allocated_to_invoice', 'allocated_to_debt']);
        });
    }
};
