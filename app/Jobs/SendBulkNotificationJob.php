<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Notification\NotificationService;
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
     */
    public function handle(NotificationService $notificationService): void
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        $customers = Customer::whereIn('id', $this->customerIds)->get();

        foreach ($customers as $customer) {
            try {
                $result = match ($this->type) {
                    'reminder' => $this->sendReminder($notificationService, $customer),
                    'overdue' => $this->sendOverdue($notificationService, $customer),
                    'isolation' => $this->sendIsolation($notificationService, $customer),
                    'broadcast' => $this->sendBroadcast($notificationService, $customer),
                    default => ['success' => false],
                };

                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }

                // Rate limiting - delay between messages
                $delay = config('notification.whatsapp.rate_limit.delay_ms', 100);
                usleep($delay * 1000);

            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Bulk notification error', [
                    'customer_id' => $customer->id,
                    'type' => $this->type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk notification completed', [
            'type' => $this->type,
            'results' => $results,
        ]);
    }

    protected function sendReminder(NotificationService $service, Customer $customer): array
    {
        $daysBeforeDue = $this->options['days_before_due'] ?? 3;
        return $service->sendPaymentReminder($customer, $daysBeforeDue);
    }

    protected function sendOverdue(NotificationService $service, Customer $customer): array
    {
        return $service->sendOverdueNotice($customer);
    }

    protected function sendIsolation(NotificationService $service, Customer $customer): array
    {
        return $service->sendIsolationNotice($customer);
    }

    protected function sendBroadcast(NotificationService $service, Customer $customer): array
    {
        $message = $this->options['message'] ?? '';

        if (empty($message)) {
            return ['success' => false, 'message' => 'No message provided'];
        }

        // Replace placeholders
        $message = str_replace(
            ['{name}', '{customer_id}', '{package}', '{debt}'],
            [
                $customer->name,
                $customer->customer_id,
                $customer->package?->name ?? '-',
                number_format($customer->total_debt, 0, ',', '.'),
            ],
            $message
        );

        return $service->sendWhatsApp($customer->phone, $message);
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
