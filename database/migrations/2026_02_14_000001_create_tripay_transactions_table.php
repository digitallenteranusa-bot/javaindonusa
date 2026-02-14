<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tripay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('reference')->unique();
            $table->string('merchant_ref')->unique();
            $table->string('method', 50);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_merchant', 12, 2)->default(0);
            $table->decimal('fee_customer', 12, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'REFUND'])->default('UNPAID');
            $table->string('checkout_url')->nullable();
            $table->string('qr_url', 500)->nullable();
            $table->string('pay_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('callback_data')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tripay_transactions');
    }
};
