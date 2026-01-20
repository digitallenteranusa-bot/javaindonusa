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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->string('settlement_number', 30)->unique();
            $table->date('settlement_date');

            // Period
            $table->date('period_start');
            $table->date('period_end');

            // Collection amounts
            $table->decimal('total_collection', 14, 2)->default(0)->comment('Total tagihan masuk');
            $table->decimal('cash_collection', 14, 2)->default(0)->comment('Total tunai');
            $table->decimal('transfer_collection', 14, 2)->default(0)->comment('Total transfer');

            // Expenses
            $table->decimal('total_expense', 14, 2)->default(0)->comment('Total pengeluaran');
            $table->decimal('approved_expense', 14, 2)->default(0)->comment('Pengeluaran disetujui');

            // Commission
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('Rate komisi %');
            $table->decimal('commission_amount', 14, 2)->default(0)->comment('Jumlah komisi');

            // Settlement amounts
            $table->decimal('expected_amount', 14, 2)->comment('Yang harus disetor');
            $table->decimal('actual_amount', 14, 2)->nullable()->comment('Yang disetor');
            $table->decimal('difference', 14, 2)->default(0)->comment('Selisih');

            // Status
            $table->enum('status', ['pending', 'settled', 'discrepancy'])->default('pending');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('settlement_date');
            $table->index(['collector_id', 'settlement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
