<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use App\Jobs\SendNotificationJob;
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

        $results = ['dispatched' => 0, 'skipped' => 0];
        $bulkDelay = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);
        $dispatchIndex = 0;

        foreach ($invoices as $invoice) {
            $customer = $invoice->customer;

            // Skip inactive customers
            if (!$customer || $customer->status === 'terminated') {
                $results['skipped']++;
                continue;
            }

            try {
                // Skip customers with no debt
                if ($customer->total_debt <= 0) {
                    $results['skipped']++;
                    continue;
                }

                // Dispatch individual job with staggered delay
                $delay = $dispatchIndex * $bulkDelay;
                SendNotificationJob::dispatch(
                    'whatsapp',
                    $customer->phone,
                    $this->buildReminderMessage($customer)
                )->onQueue(config('notification.queue.queue_name', 'notifications'))
                 ->delay(now()->addSeconds($delay));

                $results['dispatched']++;
                $dispatchIndex++;
            } catch (\Exception $e) {
                $results['skipped']++;
                Log::error('Payment reminder dispatch error', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Payment reminder jobs dispatched', [
            'days_before_due' => $this->daysBeforeDue,
            'results' => $results,
            'delay_per_message' => $bulkDelay . 's',
        ]);
    }

    /**
     * Build reminder message for individual dispatch.
     */
    protected function buildReminderMessage(Customer $customer): string
    {
        $totalDebt = number_format($customer->total_debt, 0, ',', '.');
        $dueDate = Carbon::now()->addDays($this->daysBeforeDue)->translatedFormat('d F Y');
        $urgency = $this->daysBeforeDue <= 1 ? '⚠️ *SEGERA*' : '📢 *PENGINGAT*';

        return "{$urgency}\n\nYth. Bapak/Ibu *{$customer->name}*,\n\nTagihan internet Anda sebesar *Rp {$totalDebt}* akan jatuh tempo dalam *{$this->daysBeforeDue} hari* ({$dueDate}).\n\nMohon segera lakukan pembayaran.\n\nID Pelanggan: *{$customer->customer_id}*";
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
