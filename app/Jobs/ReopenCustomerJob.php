<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReopenCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    protected int $customerId;
    protected bool $sendNotification;

    /**
     * Create a new job instance.
     */
    public function __construct(int $customerId, bool $sendNotification = true)
    {
        $this->customerId = $customerId;
        $this->sendNotification = $sendNotification;

        $this->onQueue('isolation');
    }

    /**
     * Execute the job.
     */
    public function handle(
        MikrotikService $mikrotikService,
        NotificationService $notificationService
    ): void {
        $customer = Customer::with(['router', 'package'])->find($this->customerId);

        if (!$customer) {
            Log::warning('ReopenCustomerJob: Customer not found', [
                'customer_id' => $this->customerId,
            ]);
            return;
        }

        // Skip if not isolated
        if ($customer->status !== 'isolated') {
            Log::info('ReopenCustomerJob: Customer not isolated', [
                'customer_id' => $customer->id,
            ]);
            return;
        }

        try {
            // Execute reopen on router
            $result = $mikrotikService->reopenCustomer($customer);

            if ($result['success']) {
                // Update customer status
                $customer->update([
                    'status' => 'active',
                    'isolation_date' => null,
                    'isolation_reason' => null,
                ]);

                Log::info('Customer access reopened successfully', [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                ]);

                // Send notification
                if ($this->sendNotification) {
                    $notificationService->sendAccessOpenedNotice($customer);
                }
            } else {
                Log::warning('Customer reopen failed', [
                    'customer_id' => $customer->id,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                // Retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception($result['message'] ?? 'Reopen failed');
                }
            }
        } catch (\Exception $e) {
            Log::error('ReopenCustomerJob error', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ReopenCustomerJob failed permanently', [
            'customer_id' => $this->customerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
