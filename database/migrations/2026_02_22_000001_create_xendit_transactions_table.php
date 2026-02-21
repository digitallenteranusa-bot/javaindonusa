<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xendit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->string('xendit_id')->unique()->nullable();
            $table->string('external_id')->unique();
            $table->string('method', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->enum('status', ['PENDING', 'PAID', 'EXPIRED', 'FAILED'])->default('PENDING');
            $table->string('invoice_url', 500)->nullable();
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
        Schema::dropIfExists('xendit_transactions');
    }
};
