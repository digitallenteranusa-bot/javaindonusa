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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();

            // Amount
            $table->decimal('amount', 14, 2);

            // Payment method
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'ewallet'])->default('cash');
            $table->enum('payment_channel', ['collector', 'office', 'bank', 'online'])->default('collector');

            // Bank details (for transfer)
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('reference_number', 100)->nullable()->comment('No referensi/bukti transfer');
            $table->string('transfer_proof')->nullable()->comment('Path foto bukti transfer');

            // Status
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('verified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->text('notes')->nullable();

            // Allocation tracking (JSON array of invoice allocations)
            $table->json('allocated_invoices')->nullable()->comment('Detail alokasi ke invoice');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('payment_method');
            $table->index('status');
            $table->index(['collector_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
