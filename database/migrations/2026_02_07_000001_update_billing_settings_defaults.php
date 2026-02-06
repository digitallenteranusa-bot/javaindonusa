<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update isolation_threshold dari 2 ke 3 bulan
        DB::table('settings')
            ->where('group', 'isolation')
            ->where('key', 'isolation_threshold')
            ->update([
                'key' => 'isolation_threshold_months',
                'value' => '3',
                'description' => 'Jumlah bulan tunggakan untuk isolir (pelanggan rapel dikecualikan)',
                'updated_at' => now(),
            ]);

        // Tambah setting billing_due_date jika belum ada
        DB::table('settings')->insertOrIgnore([
            'group' => 'billing',
            'key' => 'billing_due_date',
            'value' => '20',
            'type' => 'integer',
            'description' => 'Tanggal jatuh tempo tagihan setiap bulan',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tambah setting rapel_tolerance_months jika belum ada
        DB::table('settings')->insertOrIgnore([
            'group' => 'isolation',
            'key' => 'rapel_tolerance_months',
            'value' => '3',
            'type' => 'integer',
            'description' => 'Toleransi bulan untuk pelanggan dengan tipe pembayaran rapel',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update billing_grace_days key jika masih menggunakan nama lama
        DB::table('settings')
            ->where('group', 'billing')
            ->where('key', 'grace_period')
            ->update([
                'key' => 'billing_grace_days',
                'updated_at' => now(),
            ]);

        // Tambah setting recent_payment_days jika belum ada
        DB::table('settings')->insertOrIgnore([
            'group' => 'isolation',
            'key' => 'recent_payment_days',
            'value' => '30',
            'type' => 'integer',
            'description' => 'Toleransi hari jika ada pembayaran baru, tidak diisolasi',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke nilai lama
        DB::table('settings')
            ->where('group', 'isolation')
            ->where('key', 'isolation_threshold_months')
            ->update([
                'key' => 'isolation_threshold',
                'value' => '2',
                'description' => 'Jumlah bulan tunggakan untuk isolir',
                'updated_at' => now(),
            ]);

        DB::table('settings')
            ->where('group', 'billing')
            ->where('key', 'billing_grace_days')
            ->update([
                'key' => 'grace_period',
                'updated_at' => now(),
            ]);

        DB::table('settings')
            ->where('key', 'billing_due_date')
            ->delete();

        DB::table('settings')
            ->where('key', 'rapel_tolerance_months')
            ->delete();

        DB::table('settings')
            ->where('key', 'recent_payment_days')
            ->delete();
    }
};
