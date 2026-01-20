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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->comment('Grup setting: billing, notification, expense, isolation, system');
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string')->comment('Type: string, integer, float, boolean, array');
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(false)->comment('Apakah bisa diakses tanpa auth');
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
            $table->index('is_public');
        });

        // Insert default settings
        $this->seedDefaultSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }

    /**
     * Seed default settings
     */
    private function seedDefaultSettings(): void
    {
        $settings = [
            // Billing settings
            ['group' => 'billing', 'key' => 'billing_date', 'value' => '1', 'type' => 'integer', 'description' => 'Tanggal generate tagihan bulanan'],
            ['group' => 'billing', 'key' => 'due_days', 'value' => '20', 'type' => 'integer', 'description' => 'Hari jatuh tempo dari tanggal generate'],
            ['group' => 'billing', 'key' => 'grace_period', 'value' => '7', 'type' => 'integer', 'description' => 'Hari toleransi setelah jatuh tempo'],
            ['group' => 'billing', 'key' => 'late_fee', 'value' => '0', 'type' => 'float', 'description' => 'Denda keterlambatan'],
            ['group' => 'billing', 'key' => 'late_fee_type', 'value' => 'fixed', 'type' => 'string', 'description' => 'Tipe denda: fixed atau percentage'],

            // Isolation settings
            ['group' => 'isolation', 'key' => 'isolation_threshold', 'value' => '2', 'type' => 'integer', 'description' => 'Jumlah bulan tunggakan untuk isolir'],
            ['group' => 'isolation', 'key' => 'auto_isolate', 'value' => 'true', 'type' => 'boolean', 'description' => 'Aktifkan auto isolir'],
            ['group' => 'isolation', 'key' => 'isolation_time', 'value' => '08:00', 'type' => 'string', 'description' => 'Jam eksekusi isolir harian'],
            ['group' => 'isolation', 'key' => 'recent_payment_days', 'value' => '30', 'type' => 'integer', 'description' => 'Hari toleransi jika baru bayar'],

            // Expense settings
            ['group' => 'expense', 'key' => 'daily_limit', 'value' => '100000', 'type' => 'float', 'description' => 'Batas pengeluaran harian per collector'],
            ['group' => 'expense', 'key' => 'monthly_limit', 'value' => '2000000', 'type' => 'float', 'description' => 'Batas pengeluaran bulanan per collector'],
            ['group' => 'expense', 'key' => 'require_receipt', 'value' => 'true', 'type' => 'boolean', 'description' => 'Wajib upload foto nota'],

            // Notification settings
            ['group' => 'notification', 'key' => 'reminder_days', 'value' => '[3, 1]', 'type' => 'array', 'description' => 'Hari sebelum jatuh tempo untuk kirim reminder'],
            ['group' => 'notification', 'key' => 'whatsapp_enabled', 'value' => 'true', 'type' => 'boolean', 'description' => 'Aktifkan notifikasi WhatsApp'],
            ['group' => 'notification', 'key' => 'sms_enabled', 'value' => 'false', 'type' => 'boolean', 'description' => 'Aktifkan notifikasi SMS'],
            ['group' => 'notification', 'key' => 'email_enabled', 'value' => 'false', 'type' => 'boolean', 'description' => 'Aktifkan notifikasi Email'],

            // System settings
            ['group' => 'system', 'key' => 'app_name', 'value' => 'Java Indonusa Billing', 'type' => 'string', 'description' => 'Nama aplikasi', 'is_public' => true],
            ['group' => 'system', 'key' => 'timezone', 'value' => 'Asia/Jakarta', 'type' => 'string', 'description' => 'Timezone sistem'],
            ['group' => 'system', 'key' => 'currency', 'value' => 'IDR', 'type' => 'string', 'description' => 'Mata uang', 'is_public' => true],
            ['group' => 'system', 'key' => 'date_format', 'value' => 'd/m/Y', 'type' => 'string', 'description' => 'Format tanggal'],
        ];

        foreach ($settings as $setting) {
            \DB::table('settings')->insert(array_merge($setting, [
                'is_public' => $setting['is_public'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
};
