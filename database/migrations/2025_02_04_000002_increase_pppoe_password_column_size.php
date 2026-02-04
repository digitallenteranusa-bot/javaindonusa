<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Increase pppoe_password column size to accommodate encrypted values.
     * Encrypted strings are much longer than plain text due to base64 encoding
     * and additional metadata (iv, mac, tag).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Change from VARCHAR(100) to TEXT to accommodate encrypted values
            $table->text('pppoe_password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pppoe_password', 100)->nullable()->change();
        });
    }
};
