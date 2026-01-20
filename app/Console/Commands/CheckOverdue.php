<?php

namespace App\Console\Commands;

use App\Services\Billing\InvoiceService;
use App\Services\Billing\DebtIsolationService;
use Illuminate\Console\Command;

class CheckOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:check-overdue
                            {--isolate : Also process isolation for overdue customers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update overdue invoice status, optionally process isolation';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $invoiceService, DebtIsolationService $isolationService): int
    {
        $this->info("Checking overdue invoices...");

        try {
            // Update overdue status
            $result = $invoiceService->updateOverdueStatus();
            $this->info("Updated {$result['updated']} invoices to overdue status.");

            // Process isolation if flag is set
            if ($this->option('isolate')) {
                $this->info("Processing isolation for overdue customers...");

                $isolationResult = $isolationService->processIsolation();

                $this->table(
                    ['Metric', 'Count'],
                    [
                        ['Customers checked', $isolationResult['checked']],
                        ['Newly isolated', $isolationResult['isolated']],
                        ['Skipped (exceptions)', $isolationResult['skipped']],
                        ['Errors', count($isolationResult['errors'] ?? [])],
                    ]
                );

                if (!empty($isolationResult['errors'])) {
                    $this->warn("Errors occurred during isolation:");
                    foreach ($isolationResult['errors'] as $error) {
                        $this->error("  Customer {$error['customer_id']}: {$error['error']}");
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to check overdue: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
