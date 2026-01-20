<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 minutes

    protected int $daysBeforeDue;

    /**
     * Create a new job instance.
     */
    public function __construct(int $daysBeforeDue = 3)
    {
        $this->daysBeforeDue = $daysBeforeDue;
        $this->onQueue(config('notification.queue.queue_name', 'notifications'));
    }

    /**
     * Execute the job.
     * This job is meant to be scheduled daily.
     */
    public function handle(NotificationService $notificationService): void
    {
        if (!config('notification.templates.reminder.enabled', true)) {
            Log::info('Payment reminder notifications disabled');
            return;
        }

        $targetDate = Carbon::now()->addDays($this->daysBeforeDue);

        // Find invoices that are due on target date
        $invoices = Invoice::whereDate('due_date', $targetDate->toDateString())
            ->whereIn('status', ['pending', 'partial'])
            ->with('customer')
            ->get();

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;

            // Skip inactive customers
            if (!$customer || $customer->status === 'terminated') {
                $results['skipped']++;
                continue;
            }

            try {
                $result = $notificationService->sendPaymentReminder($customer, $this->daysBeforeDue);

                if ($result['success']) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

                // Rate limiting
                usleep(200000); // 200ms delay

            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Payment reminder error', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Payment reminder job completed', [
            'days_before_due' => $this->daysBeforeDue,
            'results' => $results,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendPaymentReminderJob failed', [
            'days_before_due' => $this->daysBeforeDue,
            'error' => $exception->getMessage(),
        ]);
    }
}
