<?php

namespace App\Jobs;

use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    protected string $channel;
    protected string $recipient;
    protected string $message;
    protected ?string $subject;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $channel,
        string $recipient,
        string $message,
        ?string $subject = null,
        array $options = []
    ) {
        $this->channel = $channel;
        $this->recipient = $recipient;
        $this->message = $message;
        $this->subject = $subject;
        $this->options = $options;

        $this->onQueue(config('notification.queue.queue_name', 'notifications'));
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        // Check business hours if enabled
        if (!$this->isWithinBusinessHours()) {
            // Release the job to be processed later
            $this->release(
                $this->calculateDelayUntilBusinessHours()
            );
            return;
        }

        $result = match ($this->channel) {
            'whatsapp' => $notificationService->sendWhatsApp(
                $this->recipient,
                $this->message,
                $this->options
            ),
            'sms' => $notificationService->sendSms(
                $this->recipient,
                $this->message
            ),
            'email' => $notificationService->sendEmail(
                $this->recipient,
                $this->subject ?? 'Notification',
                $this->message,
                $this->options['attachments'] ?? []
            ),
            default => ['success' => false, 'message' => 'Unknown channel'],
        };

        if (!$result['success']) {
            Log::warning('Notification job failed', [
                'channel' => $this->channel,
                'recipient' => $this->recipient,
                'error' => $result['message'] ?? 'Unknown error',
                'attempt' => $this->attempts(),
            ]);

            // Throw exception to trigger retry
            if ($this->attempts() < $this->tries) {
                throw new \Exception($result['message'] ?? 'Failed to send notification');
            }
        }

        Log::info('Notification sent', [
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'success' => $result['success'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed permanently', [
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Check if current time is within business hours
     */
    protected function isWithinBusinessHours(): bool
    {
        if (!config('notification.business_hours.enabled', true)) {
            return true;
        }

        $timezone = config('notification.business_hours.timezone', 'Asia/Jakarta');
        $now = now($timezone);

        // Skip weekends if configured
        if (config('notification.business_hours.skip_weekends', false)) {
            if ($now->isWeekend()) {
                return false;
            }
        }

        $start = config('notification.business_hours.start', '08:00');
        $end = config('notification.business_hours.end', '20:00');

        $startTime = $now->copy()->setTimeFromTimeString($start);
        $endTime = $now->copy()->setTimeFromTimeString($end);

        return $now->between($startTime, $endTime);
    }

    /**
     * Calculate seconds until next business hours window
     */
    protected function calculateDelayUntilBusinessHours(): int
    {
        $timezone = config('notification.business_hours.timezone', 'Asia/Jakarta');
        $now = now($timezone);
        $start = config('notification.business_hours.start', '08:00');

        $nextStart = $now->copy()->setTimeFromTimeString($start);

        // If we're past start time today, move to tomorrow
        if ($now->gt($nextStart)) {
            $nextStart->addDay();
        }

        // Skip to Monday if landing on weekend
        if (config('notification.business_hours.skip_weekends', false)) {
            while ($nextStart->isWeekend()) {
                $nextStart->addDay();
            }
        }

        return $now->diffInSeconds($nextStart);
    }
}
