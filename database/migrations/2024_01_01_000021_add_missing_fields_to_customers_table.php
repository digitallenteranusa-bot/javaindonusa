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
        Schema::table('customers', function (Blueprint $table) {
            // Payment behavior untuk pengecualian isolir
            $table->enum('payment_behavior', ['regular', 'rapel', 'problematic'])
                ->default('regular')
                ->after('rapel_months')
                ->comment('Kebiasaan bayar: regular=normal, rapel=bayar sekaligus, problematic=sering telat');

            // Tanggal pembayaran terakhir untuk cek pengecualian isolir
            $table->timestamp('last_payment_date')->nullable()
                ->after('payment_behavior')
                ->comment('Tanggal pembayaran terakhir');

            // Tipe koneksi (PPPoE atau Static IP)
            $table->enum('connection_type', ['pppoe', 'static', 'hotspot'])
                ->default('pppoe')
                ->after('last_payment_date')
                ->comment('Tipe koneksi: pppoe, static, hotspot');

            // IP Statis untuk pelanggan non-PPPoE
            $table->string('static_ip', 45)->nullable()
                ->after('connection_type')
                ->comment('IP Address statis untuk pelanggan non-PPPoE');

            // Index untuk optimasi query
            $table->index('payment_behavior');
            $table->index('last_payment_date');
            $table->index('connection_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['payment_behavior']);
            $table->dropIndex(['last_payment_date']);
            $table->dropIndex(['connection_type']);

            $table->dropColumn([
                'payment_behavior',
                'last_payment_date',
                'connection_type',
                'static_ip',
            ]);
        });
    }
};
