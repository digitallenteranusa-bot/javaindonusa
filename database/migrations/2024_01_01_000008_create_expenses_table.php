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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('expense_number', 30)->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('category', [
                'transport',
                'meal',
                'parking',
                'toll',
                'fuel',
                'maintenance',
                'other'
            ])->default('other');
            $table->string('description');
            $table->string('receipt_photo')->nullable()->comment('Path foto nota/struk');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->date('expense_date');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('category');
            $table->index('expense_date');
            $table->index(['user_id', 'expense_date']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
