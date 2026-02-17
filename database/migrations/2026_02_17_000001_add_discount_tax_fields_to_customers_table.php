<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('discount_type', 20)->default('none')->after('payment_behavior');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->string('discount_reason')->nullable()->after('discount_value');
            $table->boolean('is_taxed')->default(false)->after('discount_reason');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_reason', 'is_taxed']);
        });
    }
};
