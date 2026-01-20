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
        Schema::create('isp_info', function (Blueprint $table) {
            $table->id();

            // Company info
            $table->string('company_name');
            $table->string('tagline')->nullable();
            $table->string('legal_name')->nullable()->comment('Nama badan hukum');
            $table->string('npwp', 30)->nullable();
            $table->string('nib', 30)->nullable()->comment('Nomor Induk Berusaha');

            // Contact
            $table->string('phone_primary', 20);
            $table->string('phone_secondary', 20)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Address
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();

            // Payment info (JSON)
            $table->json('bank_accounts')->nullable()->comment('Array of bank accounts');
            $table->json('ewallet_accounts')->nullable()->comment('Array of e-wallet accounts');

            // Operational
            $table->json('operational_hours')->nullable()->comment('Operating hours per day');

            // Assets
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();

            // Templates
            $table->text('invoice_footer')->nullable();
            $table->text('isolation_message')->nullable()->comment('Pesan saat isolir');
            $table->text('payment_instructions')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_info');
    }
};
