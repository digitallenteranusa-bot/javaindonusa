<?php

namespace App\Console\Commands;

use App\Models\Customer;
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

        if ($isDryRun) {
            $result = $this->dryRunCheck();
        } else {
            $result = $debtService->bulkRecalculateDebt(createAdjustments: true);
        }

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total pelanggan diperiksa', $result['total']],
                [$isDryRun ? 'Tidak sinkron' : 'Tidak sinkron (diperbaiki)', $result['adjusted']],
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

    private function dryRunCheck(): array
    {
        $customers = Customer::whereIn('status', ['active', 'isolated'])->get();
        $results = [
            'total' => $customers->count(),
            'adjusted' => 0,
            'unchanged' => 0,
            'details' => [],
        ];

        foreach ($customers as $customer) {
            $calculatedDebt = $customer->calculateActualDebt();
            $currentDebt = (float) $customer->total_debt;
            $difference = $calculatedDebt - $currentDebt;

            if (abs($difference) > 0.01) {
                $results['adjusted']++;
                $results['details'][] = [
                    'customer_id' => $customer->customer_id,
                    'name' => $customer->name,
                    'previous' => $currentDebt,
                    'new' => $calculatedDebt,
                    'difference' => $difference,
                ];
            } else {
                $results['unchanged']++;
            }
        }

        return $results;
    }
}
