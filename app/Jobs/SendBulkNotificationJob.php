<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Jobs\SendNotificationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour for bulk operations

    protected string $type;
    protected array $customerIds;
    protected array $options;

    /**
     * Create a new job instance.
     *
     * @param string $type Type of notification: reminder, overdue, broadcast
     * @param array $customerIds Array of customer IDs
     * @param array $options Additional options
     */
    public function __construct(
        string $type,
        array $customerIds,
        array $options = []
    ) {
        $this->type = $type;
        $this->customerIds = $customerIds;
        $this->options = $options;

        $this->onQueue(config('notification.queue.queue_name', 'notifications'));
    }

    /**
     * Execute the job.
     * Dispatches individual SendNotificationJob with staggered delays to avoid spam detection.
     */
    public function handle(): void
    {
        $customers = Customer::whereIn('id', $this->customerIds)->get();
        $bulkDelay = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);
        $dispatched = 0;

        foreach ($customers as $index => $customer) {
            try {
                $message = $this->buildMessage($customer);

                if ($message === null) {
                    continue;
                }

                $delay = $index * $bulkDelay;
                SendNotificationJob::dispatch('whatsapp', $customer->phone, $message)
                    ->onQueue(config('notification.queue.queue_name', 'notifications'))
                    ->delay(now()->addSeconds($delay));

                $dispatched++;
            } catch (\Exception $e) {
                Log::error('Bulk notification dispatch error', [
                    'customer_id' => $customer->id,
                    'type' => $this->type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk notification jobs dispatched', [
            'type' => $this->type,
            'total_customers' => $customers->count(),
            'dispatched' => $dispatched,
            'delay_per_message' => $bulkDelay . 's',
            'estimated_duration' => ($dispatched * $bulkDelay) . 's',
        ]);
    }

    /**
     * Build the notification message for the customer based on type.
     * Returns null if the message should be skipped.
     */
    protected function buildMessage(Customer $customer): ?string
    {
        return match ($this->type) {
            'reminder' => $customer->total_debt > 0
                ? $this->buildReminderMessage($customer)
                : null,
            'overdue' => $customer->total_debt > 0
                ? $this->buildOverdueMessage($customer)
                : null,
            'isolation' => $this->buildIsolationMessage($customer),
            'broadcast' => $this->buildBroadcastMessage($customer),
            default => null,
        };
    }

    protected function buildReminderMessage(Customer $customer): string
    {
        $daysBeforeDue = $this->options['days_before_due'] ?? 3;
        return "📢 *PENGINGAT*\n\nYth. Bapak/Ibu *{$customer->name}*,\n\nTagihan internet Anda sebesar *Rp " .
            number_format($customer->total_debt, 0, ',', '.') .
            "* akan jatuh tempo dalam *{$daysBeforeDue} hari*.\n\nMohon segera lakukan pembayaran.\n\nID Pelanggan: *{$customer->customer_id}*";
    }

    protected function buildOverdueMessage(Customer $customer): string
    {
        return "⚠️ *TAGIHAN JATUH TEMPO*\n\nYth. Bapak/Ibu *{$customer->name}*,\n\nTagihan internet Anda sebesar *Rp " .
            number_format($customer->total_debt, 0, ',', '.') .
            "* telah melewati jatuh tempo.\n\nMohon segera lakukan pembayaran.\n\nID Pelanggan: *{$customer->customer_id}*";
    }

    protected function buildIsolationMessage(Customer $customer): string
    {
        return "🔴 *PEMBERITAHUAN ISOLIR*\n\nYth. Bapak/Ibu *{$customer->name}*,\n\nLayanan internet Anda telah *DIISOLIR* karena tunggakan pembayaran sebesar *Rp " .
            number_format($customer->total_debt, 0, ',', '.') . "*.\n\nID Pelanggan: *{$customer->customer_id}*";
    }

    protected function buildBroadcastMessage(Customer $customer): ?string
    {
        $message = $this->options['message'] ?? '';

        if (empty($message)) {
            return null;
        }

        return str_replace(
            ['{name}', '{customer_id}', '{package}', '{debt}'],
            [
                $customer->name,
                $customer->customer_id,
                $customer->package?->name ?? '-',
                number_format($customer->total_debt, 0, ',', '.'),
            ],
            $message
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk notification job failed', [
            'type' => $this->type,
            'customer_count' => count($this->customerIds),
            'error' => $exception->getMessage(),
        ]);
    }
}
