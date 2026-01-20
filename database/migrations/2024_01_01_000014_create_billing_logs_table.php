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
        Schema::create('billing_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->string('loggable_type')->nullable()->comment('Model class name');
            $table->unsignedBigInteger('loggable_id')->nullable()->comment('Model ID');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Action details
            $table->string('action', 50)->comment('Action yang dilakukan');
            $table->text('description')->nullable();

            // Data changes
            $table->json('old_data')->nullable()->comment('Data sebelum perubahan');
            $table->json('new_data')->nullable()->comment('Data setelah perubahan');

            // Request info
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('action');
            $table->index(['loggable_type', 'loggable_id']);
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_logs');
    }
};
