<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailyIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessIsolation extends Command
{
    protected $signature = 'billing:process-isolation
                            {--dry-run : Show what would be isolated without executing}
                            {--sync : Run synchronously instead of queuing}';

    protected $description = 'Process daily automatic customer isolation based on debt threshold';

    public function handle(): int
    {
        if (!config('mikrotik.auto_isolation.enabled', true)) {
            $this->warn('Auto isolation is disabled in configuration');
            return Command::SUCCESS;
        }

        $thresholdMonths = config('mikrotik.auto_isolation.threshold_months', 2);
        $gracePeriodDays = config('mikrotik.auto_isolation.grace_period_days', 7);
        $recentPaymentDays = config('mikrotik.auto_isolation.recent_payment_days', 30);
        $excludeRapel = config('mikrotik.auto_isolation.exclude_rapel', true);

        $this->info('Isolation Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Threshold Months', $thresholdMonths],
            ['Grace Period Days', $gracePeriodDays],
            ['Recent Payment Days', $recentPaymentDays],
            ['Exclude Rapel', $excludeRapel ? 'Yes' : 'No'],
        ]);

        $this->newLine();

        // Get customers to evaluate
        $thresholdDate = now()->subMonths($thresholdMonths)->subDays($gracePeriodDays);

        $customers = Customer::where('status', 'active')
            ->where('total_debt', '>', 0)
            ->whereHas('invoices', function ($query) use ($thresholdDate) {
                $query->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->where('due_date', '<', $thresholdDate);
            })
            ->with(['router', 'package'])
            ->get();

        $this->info("Found {$customers->count()} customer(s) to evaluate");
        $this->newLine();

        $toIsolate = [];
        $skipped = [
            'rapel' => [],
            'recent_payment' => [],
            'no_router' => [],
        ];

        foreach ($customers as $customer) {
            // Check rapel
            if ($excludeRapel && $customer->is_rapel && $customer->rapel_months > 0) {
                $skipped['rapel'][] = $customer;
                continue;
            }

            // Check recent payment
            $recentPayment = Payment::where('customer_id', $customer->id)
                ->where('created_at', '>=', now()->subDays($recentPaymentDays))
                ->exists();

            if ($recentPayment) {
                $skipped['recent_payment'][] = $customer;
                continue;
            }

            // Check router
            if (!$customer->router) {
                $skipped['no_router'][] = $customer;
                continue;
            }

            $toIsolate[] = $customer;
        }

        // Show summary
        $this->info('Summary:');
        $this->table(['Category', 'Count'], [
            ['To Isolate', count($toIsolate)],
            ['Skipped (Rapel)', count($skipped['rapel'])],
            ['Skipped (Recent Payment)', count($skipped['recent_payment'])],
            ['Skipped (No Router)', count($skipped['no_router'])],
        ]);

        if (!empty($toIsolate)) {
            $this->newLine();
            $this->info('Customers to isolate:');
            $this->table(
                ['ID', 'Name', 'Customer ID', 'Debt', 'Router'],
                collect($toIsolate)->map(fn($c) => [
                    $c->id,
                    $c->name,
                    $c->customer_id,
                    'Rp ' . number_format($c->total_debt, 0, ',', '.'),
                    $c->router?->name ?? '-',
                ])->toArray()
            );
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Dry run mode - no changes made');
            return Command::SUCCESS;
        }

        if (empty($toIsolate)) {
            $this->info('No customers to isolate');
            return Command::SUCCESS;
        }

        if (!$this->confirm('Proceed with isolation?')) {
            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('Processing isolation synchronously...');
            $job = new ProcessDailyIsolationJob();
            $job->handle(
                app(\App\Services\Mikrotik\MikrotikService::class),
                app(\App\Services\Notification\NotificationService::class)
            );
            $this->info('✓ Isolation process completed');
        } else {
            ProcessDailyIsolationJob::dispatch();
            $this->info('✓ Isolation job dispatched to queue');
        }

        return Command::SUCCESS;
    }
}
