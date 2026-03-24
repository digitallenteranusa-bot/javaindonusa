<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_debt_amount', 12, 2); // total hutang saat plan dibuat
            $table->unsignedSmallInteger('installment_count'); // jumlah cicilan
            $table->decimal('installment_amount', 12, 2); // jumlah per cicilan
            $table->decimal('paid_amount', 12, 2)->default(0); // sudah dibayar
            $table->decimal('remaining_amount', 12, 2); // sisa
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'cancelled', 'defaulted'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number'); // cicilan ke-N
            $table->decimal('amount', 12, 2); // jumlah yang harus dibayar
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue'])->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payment_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};
