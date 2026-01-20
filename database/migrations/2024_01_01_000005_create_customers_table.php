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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 20)->unique()->comment('ID Pelanggan (e.g., JI-0001)');
            $table->string('name');
            $table->text('address');
            $table->string('rt_rw', 20)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('phone', 20);
            $table->string('phone_alt', 20)->nullable()->comment('Nomor HP alternatif');
            $table->string('email')->nullable();
            $table->string('nik', 20)->nullable()->comment('NIK KTP');

            // Foreign keys
            $table->foreignId('package_id')->constrained();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();

            // PPPoE & Network
            $table->string('pppoe_username', 100)->nullable()->unique();
            $table->string('pppoe_password', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('onu_serial', 50)->nullable()->comment('ONU Serial Number untuk GPON');

            // Status
            $table->enum('status', ['active', 'isolated', 'suspended', 'terminated'])->default('active');
            $table->decimal('total_debt', 14, 2)->default(0)->comment('Total hutang pelanggan');

            // Dates
            $table->date('join_date');
            $table->timestamp('isolation_date')->nullable();
            $table->string('isolation_reason')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason')->nullable();

            // Billing settings
            $table->enum('billing_type', ['prepaid', 'postpaid'])->default('postpaid');
            $table->unsignedTinyInteger('billing_date')->default(1)->comment('Tanggal generate tagihan (1-28)');

            // Rapel (Installment)
            $table->boolean('is_rapel')->default(false)->comment('Pelanggan rapel/nyicil');
            $table->decimal('rapel_amount', 12, 2)->nullable()->comment('Jumlah cicilan per bulan');
            $table->unsignedTinyInteger('rapel_months')->nullable()->comment('Sisa bulan cicilan');

            // Additional
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('phone');
            $table->index('total_debt');
            $table->index(['collector_id', 'status']);
            $table->index(['area_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
