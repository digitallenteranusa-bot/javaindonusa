<?php

namespace App\Console\Commands;

use App\Services\Billing\DebtService;
use Illuminate\Console\Command;

class SyncCustomerDebt extends Command
{
    protected $signature = 'billing:sync-debt
                            {--dry-run : Tampilkan yang tidak sinkron tanpa mengubah data}';

    protected $description = 'Sinkronkan total_debt pelanggan dengan invoice aktual';

    public function handle(DebtService $debtService): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Mode dry-run: hanya menampilkan data, tidak mengubah apapun.');
        }

        $this->info('Memeriksa sinkronisasi total_debt semua pelanggan aktif & isolir...');

        $result = $debtService->bulkRecalculateDebt(createAdjustments: !$isDryRun);

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total pelanggan diperiksa', $result['total']],
                ['Tidak sinkron (diperbaiki)', $result['adjusted']],
                ['Sudah benar', $result['unchanged']],
            ]
        );

        if (!empty($result['details'])) {
            $this->newLine();
            $this->warn($isDryRun ? 'Pelanggan yang tidak sinkron:' : 'Pelanggan yang telah diperbaiki:');

            $tableData = [];
            foreach ($result['details'] as $detail) {
                $tableData[] = [
                    $detail['customer_id'],
                    $detail['name'],
                    number_format($detail['previous'], 0, ',', '.'),
                    number_format($detail['new'], 0, ',', '.'),
                    ($detail['difference'] >= 0 ? '+' : '') . number_format($detail['difference'], 0, ',', '.'),
                ];
            }

            $this->table(
                ['ID', 'Nama', 'Sebelum', 'Seharusnya', 'Selisih'],
                $tableData
            );
        }

        if ($result['adjusted'] === 0) {
            $this->info('Semua data total_debt sudah sinkron.');
        } elseif ($isDryRun) {
            $this->warn("Jalankan tanpa --dry-run untuk memperbaiki {$result['adjusted']} pelanggan.");
        } else {
            $this->info("Berhasil memperbaiki {$result['adjusted']} pelanggan.");
        }

        return Command::SUCCESS;
    }
}
