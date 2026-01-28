<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetAllData extends Command
{
    protected $signature = 'data:reset
                            {--customers : Reset hanya data pelanggan}
                            {--transactions : Reset hanya data transaksi (invoice, payment)}
                            {--master : Reset data master (area, paket, router)}
                            {--genieacs : Reset data GenieACS/perangkat CPE}
                            {--all : Reset semua data (kecuali users admin)}
                            {--force : Skip konfirmasi}';

    protected $description = 'Reset data aplikasi untuk memulai dari awal';

    public function handle()
    {
        $this->warn('╔════════════════════════════════════════════════════════════╗');
        $this->warn('║           RESET DATA - ISP BILLING SYSTEM                  ║');
        $this->warn('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Determine what to reset
        $resetCustomers = $this->option('customers');
        $resetTransactions = $this->option('transactions');
        $resetMaster = $this->option('master');
        $resetGenieacs = $this->option('genieacs');
        $resetAll = $this->option('all');

        // If no option specified, show menu
        if (!$resetCustomers && !$resetTransactions && !$resetMaster && !$resetGenieacs && !$resetAll) {
            $choice = $this->choice(
                'Pilih data yang akan direset:',
                [
                    '1' => 'Pelanggan saja (customers, invoices, payments, devices)',
                    '2' => 'Transaksi saja (invoices, payments, expenses, settlements)',
                    '3' => 'Data Master (areas, packages, routers, ODPs, OLTs)',
                    '4' => 'GenieACS/Perangkat CPE saja',
                    '5' => 'SEMUA DATA (kecuali admin user)',
                    '6' => 'Batal',
                ],
                '6'
            );

            match ($choice) {
                '1' => $resetCustomers = true,
                '2' => $resetTransactions = true,
                '3' => $resetMaster = true,
                '4' => $resetGenieacs = true,
                '5' => $resetAll = true,
                default => null,
            };

            if ($choice === '6') {
                $this->info('Dibatalkan.');
                return 0;
            }
        }

        // Confirmation
        if (!$this->option('force')) {
            $this->error('⚠️  PERINGATAN: Data yang dihapus TIDAK DAPAT dikembalikan!');
            $this->newLine();

            if (!$this->confirm('Apakah Anda yakin ingin melanjutkan?', false)) {
                $this->info('Dibatalkan.');
                return 0;
            }

            // Double confirmation for --all
            if ($resetAll) {
                $this->error('⚠️  Anda akan menghapus SEMUA DATA!');
                $confirmText = $this->ask('Ketik "HAPUS SEMUA" untuk konfirmasi');

                if ($confirmText !== 'HAPUS SEMUA') {
                    $this->info('Dibatalkan.');
                    return 0;
                }
            }
        }

        $this->newLine();
        $this->info('Memulai proses reset...');
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            if ($resetAll || $resetTransactions) {
                $this->resetTransactions();
            }

            if ($resetAll || $resetCustomers) {
                $this->resetCustomers();
            }

            if ($resetAll || $resetMaster) {
                $this->resetMaster();
            }

            if ($resetAll || $resetGenieacs) {
                $this->resetGenieacs();
            }

            if ($resetAll) {
                $this->resetOthers();
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->newLine();
            $this->info('╔════════════════════════════════════════════════════════════╗');
            $this->info('║              ✅ RESET SELESAI                              ║');
            $this->info('╚════════════════════════════════════════════════════════════╝');
            $this->newLine();

            $this->table(
                ['Langkah Selanjutnya'],
                [
                    ['1. Tambah data Area di menu Admin > Master Data > Area'],
                    ['2. Tambah data Paket di menu Admin > Master Data > Paket'],
                    ['3. Tambah data Router di menu Admin > Master Data > Router'],
                    ['4. Tambah data Penagih di menu Admin > Users'],
                    ['5. Import atau tambah Pelanggan'],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    protected function resetTransactions(): void
    {
        $this->info('🗑️  Menghapus data transaksi...');

        $tables = [
            'debt_histories' => 'Riwayat Hutang',
            'invoice_payment' => 'Alokasi Pembayaran',
            'payments' => 'Pembayaran',
            'invoices' => 'Invoice',
            'expenses' => 'Pengeluaran',
            'settlements' => 'Setoran',
            'collection_logs' => 'Log Penagihan',
        ];

        foreach ($tables as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("   ✓ {$label}: {$count} data dihapus");
            }
        }
    }

    protected function resetCustomers(): void
    {
        $this->info('🗑️  Menghapus data pelanggan...');

        // Reset related tables first
        $relatedTables = [
            'debt_histories',
            'invoice_payment',
            'payments',
            'invoices',
            'collection_logs',
            'customer_devices',
            'customer_tokens',
        ];

        foreach ($relatedTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // Reset customers
        if (Schema::hasTable('customers')) {
            $count = DB::table('customers')->count();
            DB::table('customers')->truncate();
            $this->line("   ✓ Pelanggan: {$count} data dihapus");
        }
    }

    protected function resetMaster(): void
    {
        $this->info('🗑️  Menghapus data master...');

        // Must reset customers first due to foreign keys
        if (Schema::hasTable('customers')) {
            $this->resetCustomers();
        }

        $tables = [
            'odps' => 'ODP',
            'olts' => 'OLT',
            'vpn_configs' => 'Konfigurasi VPN',
            'routers' => 'Router',
            'areas' => 'Area',
            'packages' => 'Paket',
            'radius_servers' => 'Radius Server',
        ];

        foreach ($tables as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("   ✓ {$label}: {$count} data dihapus");
            }
        }
    }

    protected function resetGenieacs(): void
    {
        $this->info('🗑️  Menghapus data GenieACS/Perangkat CPE...');

        $tables = [
            'customer_devices' => 'Perangkat Pelanggan (CPE)',
            'customer_tokens' => 'Token Pelanggan',
        ];

        foreach ($tables as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("   ✓ {$label}: {$count} data dihapus");
            }
        }
    }

    protected function resetOthers(): void
    {
        $this->info('🗑️  Menghapus data lainnya...');

        $tables = [
            'audit_logs' => 'Audit Log',
            'notifications' => 'Notifikasi',
            'customer_devices' => 'Perangkat Pelanggan',
            'customer_tokens' => 'Token Pelanggan',
        ];

        foreach ($tables as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("   ✓ {$label}: {$count} data dihapus");
            }
        }

        // Reset non-admin users (keep admin)
        if (Schema::hasTable('users')) {
            $count = DB::table('users')->where('role', '!=', 'admin')->count();
            DB::table('users')->where('role', '!=', 'admin')->delete();
            $this->line("   ✓ Users (non-admin): {$count} data dihapus");
        }
    }
}
