<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use App\Services\Radius\RadiusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessDailyIsolationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour

    /**
     * Execute the job.
     */
    public function handle(
        MikrotikService $mikrotikService,
        NotificationService $notificationService
    ): void {
        if (!config('mikrotik.auto_isolation.enabled', true)) {
            Log::info('Auto isolation is disabled');
            return;
        }

        Log::info('Starting daily isolation process');

        $thresholdMonths = config('mikrotik.auto_isolation.threshold_months', 2);
        $gracePeriodDays = config('mikrotik.auto_isolation.grace_period_days', 7);
        $recentPaymentDays = config('mikrotik.auto_isolation.recent_payment_days', 30);
        $excludeRapel = config('mikrotik.auto_isolation.exclude_rapel', true);

        $results = [
            'isolated' => 0,
            'skipped_rapel' => 0,
            'skipped_recent_payment' => 0,
            'skipped_already_isolated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $customers = Customer::where('status', 'active')
            ->where('total_debt', '>', 0)
            ->with(['router', 'package', 'invoices' => function ($query) {
                $query->whereIn('status', ['pending', 'partial', 'overdue']);
            }])
            ->get();

        Log::info("Found {$customers->count()} customers to evaluate for isolation");

        foreach ($customers as $customer) {
            try {
                $isRapel = $customer->is_rapel || $customer->payment_behavior === 'rapel';
                if ($excludeRapel && $isRapel) {
                    $results['skipped_rapel']++;
                    continue;
                }

                $recentPayment = Payment::where('customer_id', $customer->id)
                    ->where('status', 'verified')
                    ->where('created_at', '>=', now()->subDays($recentPaymentDays))
                    ->exists();

                if ($recentPayment) {
                    $results['skipped_recent_payment']++;
                    continue;
                }

                $consecutiveMonths = $this->countConsecutiveUnpaidMonths($customer, $gracePeriodDays);
                if ($consecutiveMonths < $thresholdMonths) {
                    continue;
                }

                // RADIUS: update DB dulu SEBELUM disconnect session
                try {
                    app(RadiusService::class)->isolateCustomer($customer);
                } catch (\Exception $e) {
                    Log::warning('Auto-isolation: RADIUS sync failed', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $result = $mikrotikService->isolateCustomer($customer);

                if ($result['success']) {
                    $customer->update([
                        'status' => 'isolated',
                        'isolation_date' => now(),
                        'isolation_reason' => "Auto-isolir: Tunggakan {$consecutiveMonths} bulan berturut-turut",
                    ]);

                    $notificationService->sendAsync(
                        'whatsapp',
                        $customer->phone,
                        $this->buildIsolationMessage($customer)
                    );

                    $results['isolated']++;

                    Log::info("Customer isolated", [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'consecutive_months' => $consecutiveMonths,
                        'total_debt' => $customer->total_debt,
                    ]);
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'customer_id' => $customer->id,
                        'error' => $result['message'] ?? 'Unknown error',
                    ];
                }

                usleep(100000);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ];

                Log::error("Failed to isolate customer", [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Daily isolation process completed", $results);
    }

    protected function countConsecutiveUnpaidMonths(Customer $customer, int $graceDays): int
    {
        $now = Carbon::now();

        $overdueInvoices = $customer->invoices->filter(function ($invoice) use ($now, $graceDays) {
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

    /**
     * Build isolation notification message
     */
    protected function buildIsolationMessage(Customer $customer): string
    {
        $graceDays = config('mikrotik.auto_isolation.grace_period_days', 7);
        $overdueDebt = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_date', '<', now()->subDays($graceDays))
            ->sum('remaining_amount');

        $totalDebt = number_format($overdueDebt ?: $customer->total_debt, 0, ',', '.');

        return "🔴 *PEMBERITAHUAN ISOLIR*\n\n" .
            "Yth. Bapak/Ibu *{$customer->name}*,\n\n" .
            "Layanan internet Anda telah *DIISOLIR* karena tunggakan pembayaran sebesar *Rp {$totalDebt}*.\n\n" .
            "Silakan segera melakukan pembayaran untuk mengaktifkan kembali layanan.\n\n" .
            "Hubungi kami untuk informasi lebih lanjut.";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessDailyIsolationJob failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
