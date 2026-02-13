<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'salary',
                'rent',
                'electricity',
                'internet',
                'equipment',
                'maintenance',
                'other',
            ]);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->string('receipt_photo')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('category');
            $table->index('expense_date');
            $table->index(['period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');
    }
};
