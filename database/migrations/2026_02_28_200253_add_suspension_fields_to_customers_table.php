<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('suspension_start_date')->nullable()->after('isolation_reason');
            $table->date('suspension_end_date')->nullable()->after('suspension_start_date');
            $table->string('suspension_reason')->nullable()->after('suspension_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['suspension_start_date', 'suspension_end_date', 'suspension_reason']);
        });
    }
};
