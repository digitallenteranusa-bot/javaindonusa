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
        Schema::create('debt_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // Transaction type
            $table->enum('type', [
                'charge',           // Tagihan baru
                'payment',          // Pembayaran
                'adjustment_add',   // Penambahan manual
                'adjustment_subtract', // Pengurangan manual
                'discount',         // Diskon
                'late_fee',         // Denda keterlambatan
                'writeoff'          // Penghapusan piutang
            ]);

            // Amounts
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2)->comment('Saldo sebelum transaksi');
            $table->decimal('balance_after', 14, 2)->comment('Saldo setelah transaksi');

            // Details
            $table->string('description');
            $table->string('reference_number')->nullable()->comment('No referensi (invoice/payment number)');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index(['customer_id', 'created_at']);
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_histories');
    }
};
