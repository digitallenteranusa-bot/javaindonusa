<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailyIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProcessIsolation extends Command
{
    protected $signature = 'billing:process-isolation
                            {--dry-run : Show what would be isolated without executing}
                            {--sync : Run synchronously instead of queuing}
                            {--force : Skip confirmation prompt (used by scheduler)}';

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

        $customers = Customer::where('status', 'active')
            ->where('total_debt', '>', 0)
            ->with(['router', 'package', 'invoices' => function ($query) {
                $query->whereIn('status', ['pending', 'partial', 'overdue']);
            }])
            ->get();

        $this->info("Found {$customers->count()} customer(s) to evaluate");
        $this->newLine();

        $toIsolate = [];
        $skipped = [
            'rapel' => [],
            'recent_payment' => [],
            'no_router' => [],
            'not_enough_months' => 0,
        ];

        foreach ($customers as $customer) {
            $isRapel = $customer->is_rapel || $customer->payment_behavior === 'rapel';
            if ($excludeRapel && $isRapel) {
                $skipped['rapel'][] = $customer;
                continue;
            }

            $recentPayment = Payment::where('customer_id', $customer->id)
                ->where('status', 'verified')
                ->where('created_at', '>=', now()->subDays($recentPaymentDays))
                ->exists();

            if ($recentPayment) {
                $skipped['recent_payment'][] = $customer;
                continue;
            }

            if (!$customer->router) {
                $skipped['no_router'][] = $customer;
                continue;
            }

            $consecutiveMonths = $this->countConsecutiveUnpaidMonths($customer->invoices, $gracePeriodDays);
            if ($consecutiveMonths < $thresholdMonths) {
                $skipped['not_enough_months']++;
                continue;
            }

            $toIsolate[] = ['customer' => $customer, 'months' => $consecutiveMonths];
        }

        $this->info('Summary:');
        $this->table(['Category', 'Count'], [
            ['To Isolate', count($toIsolate)],
            ['Skipped (Rapel)', count($skipped['rapel'])],
            ['Skipped (Recent Payment)', count($skipped['recent_payment'])],
            ['Skipped (No Router)', count($skipped['no_router'])],
            ['Skipped (< ' . $thresholdMonths . ' bulan)', $skipped['not_enough_months']],
        ]);

        if (!empty($toIsolate)) {
            $this->newLine();
            $this->info('Customers to isolate:');
            $this->table(
                ['ID', 'Name', 'Customer ID', 'Tunggakan', 'Debt', 'Router'],
                collect($toIsolate)->map(fn($item) => [
                    $item['customer']->id,
                    $item['customer']->name,
                    $item['customer']->customer_id,
                    $item['months'] . ' bulan',
                    'Rp ' . number_format($item['customer']->total_debt, 0, ',', '.'),
                    $item['customer']->router?->name ?? '-',
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

        if (!$this->option('force') && !$this->confirm('Proceed with isolation?')) {
            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('Processing isolation synchronously...');
            $job = new ProcessDailyIsolationJob();
            $job->handle(
                app(\App\Services\Mikrotik\MikrotikService::class),
                app(\App\Services\Notification\NotificationService::class)
            );
            $this->info('Isolation process completed');
        } else {
            ProcessDailyIsolationJob::dispatch();
            $this->info('Isolation job dispatched to queue');
        }

        return Command::SUCCESS;
    }

    protected function countConsecutiveUnpaidMonths(Collection $invoices, int $graceDays): int
    {
        $now = Carbon::now();

        $overdueInvoices = $invoices->filter(function ($invoice) use ($now, $graceDays) {
            $gracePeriodEnd = Carbon::parse($invoice->due_date)->addDays($graceDays);
            return $now->isAfter($gracePeriodEnd);
        });

        if ($overdueInvoices->isEmpty()) {
            return 0;
        }

        $sorted = $overdueInvoices->sortByDesc(function ($invoice) {
            return $invoice->period_year * 100 + $invoice->period_month;
        });

        $consecutive = 0;
        $previousPeriod = null;

        foreach ($sorted as $invoice) {
            $currentPeriod = Carbon::create($invoice->period_year, $invoice->period_month, 1);

            if ($previousPeriod === null) {
                $consecutive = 1;
                $previousPeriod = $currentPeriod;
                continue;
            }

            if ($currentPeriod->isSameMonth($previousPeriod->copy()->subMonth())) {
                $consecutive++;
                $previousPeriod = $currentPeriod;
            } else {
                break;
            }
        }

        return $consecutive;
    }
}
