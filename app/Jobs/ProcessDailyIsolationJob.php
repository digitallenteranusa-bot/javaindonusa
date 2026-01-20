<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
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

        // Get customers that should be isolated
        $customersToIsolate = $this->getCustomersToIsolate(
            $thresholdMonths,
            $gracePeriodDays,
            $recentPaymentDays,
            $excludeRapel
        );

        Log::info("Found {$customersToIsolate->count()} customers to evaluate for isolation");

        foreach ($customersToIsolate as $customer) {
            try {
                // Skip if already isolated
                if ($customer->status === 'isolated') {
                    $results['skipped_already_isolated']++;
                    continue;
                }

                // Skip rapel customers if configured
                if ($excludeRapel && $customer->is_rapel && $customer->rapel_months > 0) {
                    $results['skipped_rapel']++;
                    Log::info("Skipping rapel customer", ['customer_id' => $customer->id]);
                    continue;
                }

                // Check for recent payment
                $recentPayment = Payment::where('customer_id', $customer->id)
                    ->where('created_at', '>=', now()->subDays($recentPaymentDays))
                    ->exists();

                if ($recentPayment) {
                    $results['skipped_recent_payment']++;
                    Log::info("Skipping customer with recent payment", ['customer_id' => $customer->id]);
                    continue;
                }

                // Execute isolation
                $result = $mikrotikService->isolateCustomer($customer);

                if ($result['success']) {
                    // Update customer status
                    $customer->update([
                        'status' => 'isolated',
                        'isolation_date' => now(),
                        'isolation_reason' => "Auto-isolir: Tunggakan {$thresholdMonths}+ bulan",
                    ]);

                    // Send notification
                    $notificationService->sendAsync(
                        'whatsapp',
                        $customer->phone,
                        $this->buildIsolationMessage($customer)
                    );

                    $results['isolated']++;

                    Log::info("Customer isolated", [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'total_debt' => $customer->total_debt,
                    ]);
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'customer_id' => $customer->id,
                        'error' => $result['message'] ?? 'Unknown error',
                    ];
                }

                // Rate limiting
                usleep(100000); // 100ms delay between operations

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

    /**
     * Get customers that should be isolated
     */
    protected function getCustomersToIsolate(
        int $thresholdMonths,
        int $gracePeriodDays,
        int $recentPaymentDays,
        bool $excludeRapel
    ) {
        // Calculate threshold date
        $thresholdDate = now()->subMonths($thresholdMonths)->subDays($gracePeriodDays);

        return Customer::where('status', 'active')
            ->where('total_debt', '>', 0)
            ->whereHas('invoices', function ($query) use ($thresholdDate) {
                $query->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->where('due_date', '<', $thresholdDate);
            })
            ->when($excludeRapel, function ($query) {
                // Include rapel customers but we'll filter them in the loop
                // to provide proper logging
            })
            ->with(['router', 'package'])
            ->get();
    }

    /**
     * Build isolation notification message
     */
    protected function buildIsolationMessage(Customer $customer): string
    {
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');

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
