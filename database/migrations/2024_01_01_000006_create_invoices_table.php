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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Period
            $table->unsignedTinyInteger('period_month')->comment('Bulan tagihan (1-12)');
            $table->unsignedSmallInteger('period_year')->comment('Tahun tagihan');

            // Package snapshot (untuk historical record)
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_name')->comment('Nama paket saat generate');
            $table->decimal('package_price', 12, 2)->comment('Harga paket saat generate');

            // Amounts
            $table->decimal('additional_charges', 12, 2)->default(0)->comment('Biaya tambahan');
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('total_amount', 14, 2)->comment('Total tagihan');
            $table->decimal('paid_amount', 14, 2)->default(0)->comment('Jumlah yang sudah dibayar');
            $table->decimal('remaining_amount', 14, 2)->comment('Sisa tagihan');

            // Status
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'cancelled'])->default('pending');

            // Dates
            $table->date('due_date')->comment('Jatuh tempo');
            $table->timestamp('paid_at')->nullable()->comment('Waktu lunas');
            $table->timestamp('generated_at')->nullable()->comment('Waktu generate invoice');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('due_date');
            $table->index(['customer_id', 'status']);
            $table->index(['period_year', 'period_month']);
            $table->unique(['customer_id', 'period_month', 'period_year'], 'unique_customer_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
