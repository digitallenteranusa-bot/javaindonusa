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
        Schema::create('collection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // Action
            $table->enum('action_type', [
                'visit',
                'payment_cash',
                'payment_transfer',
                'not_home',
                'refused',
                'promise_to_pay',
                'rescheduled',
                'reminder_sent'
            ]);

            // Payment details (if applicable)
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('transfer_proof')->nullable();

            // Visit details
            $table->timestamp('visit_time')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->text('notes')->nullable();
            $table->json('device_info')->nullable()->comment('Info device penagih');

            $table->timestamps();

            // Indexes
            $table->index('action_type');
            $table->index('visit_time');
            $table->index(['collector_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['collector_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_logs');
    }
};
