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
        Schema::table('debt_histories', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->index('payment_id');
        });

        Schema::table('collection_logs', function (Blueprint $table) {
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_histories', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['payment_id']);
        });

        Schema::table('collection_logs', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
        });
    }
};
