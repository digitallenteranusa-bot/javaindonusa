<?php

namespace App\Console\Commands;

use App\Services\Billing\InvoiceService;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate-invoices
                            {--month= : Month to generate invoices for (1-12)}
                            {--year= : Year to generate invoices for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly invoices for all active customers';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $invoiceService): int
    {
        $month = $this->option('month') ?? now()->month;
        $year = $this->option('year') ?? now()->year;

        $this->info("Generating invoices for {$month}/{$year}...");

        try {
            $result = $invoiceService->generateMonthlyInvoices((int) $month, (int) $year);

            $this->info("Invoice generation completed!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Generated', $result['generated']],
                    ['Skipped (already exists)', $result['skipped']],
                    ['Errors', count($result['errors'])],
                ]
            );

            if (!empty($result['errors'])) {
                $this->warn("Errors occurred:");
                foreach ($result['errors'] as $error) {
                    $this->error("  Customer {$error['customer_id']}: {$error['error']}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate invoices: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
