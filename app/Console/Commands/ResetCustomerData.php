<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetCustomerData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'data:reset-customers
                            {--force : Skip confirmation prompt}
                            {--keep-master : Keep master data (packages, areas, routers, users)}';

    /**
     * The console command description.
     */
    protected $description = 'Reset semua data pelanggan, invoice, pembayaran, dan data terkait. HATI-HATI: Data akan dihapus permanen!';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('==========================================');
        $this->warn('  PERINGATAN: RESET DATA PELANGGAN');
        $this->warn('==========================================');
        $this->newLine();

        $tablesToReset = [
            'collection_logs' => 'Log Penagihan',
            'debt_histories' => 'Histori Hutang',
            'payment_invoice' => 'Alokasi Pembayaran',
            'payments' => 'Pembayaran',
            'invoices' => 'Invoice',
            'settlements' => 'Setoran',
            'expenses' => 'Pengeluaran',
            'customers' => 'Pelanggan',
        ];

        $this->info('Data yang akan dihapus:');
        foreach ($tablesToReset as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  - {$label}: {$count} records");
            }
        }

        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('Apakah Anda yakin ingin menghapus SEMUA data pelanggan? Tindakan ini TIDAK DAPAT DIBATALKAN!', false)) {
                $this->info('Operasi dibatalkan.');
                return 0;
            }

            // Double confirmation
            $confirm = $this->ask('Ketik "HAPUS SEMUA DATA" untuk konfirmasi');
            if ($confirm !== 'HAPUS SEMUA DATA') {
                $this->info('Konfirmasi tidak sesuai. Operasi dibatalkan.');
                return 0;
            }
        }

        $this->newLine();
        $this->info('Memulai proses reset...');

        DB::beginTransaction();

        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($tablesToReset as $table => $label) {
                if (Schema::hasTable($table)) {
                    $this->line("  Menghapus {$label}...");
                    DB::table($table)->truncate();
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();

            $this->newLine();
            $this->info('==========================================');
            $this->info('  DATA BERHASIL DIRESET!');
            $this->info('==========================================');
            $this->newLine();

            if (!$this->option('keep-master')) {
                $this->warn('Master data (packages, areas, routers, users) TIDAK dihapus.');
                $this->warn('Jalankan dengan --keep-master=false untuk menghapus semuanya.');
            }

            $this->info('Anda dapat menjalankan seeder untuk data sample:');
            $this->line('  php artisan db:seed --class=CustomerSeeder');

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return 1;
        }
    }
}
