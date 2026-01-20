<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentReminderJob;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:send-reminders
                            {--days=* : Days before due date to send reminders (default: 3,1)}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     */
    protected $description = 'Send payment reminder notifications to customers with upcoming due dates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysOption = $this->option('days');

        // Default reminder days: 3 days and 1 day before due
        $reminderDays = !empty($daysOption)
            ? array_map('intval', $daysOption)
            : config('notification.templates.reminder.days_before', [3, 1]);

        $this->info('Starting payment reminder process...');
        $this->newLine();

        foreach ($reminderDays as $days) {
            $this->info("Processing reminders for invoices due in {$days} day(s)...");

            if ($this->option('sync')) {
                // Run synchronously
                $job = new SendPaymentReminderJob($days);
                $job->handle(app(\App\Services\Notification\NotificationService::class));
                $this->info("  ✓ Completed (sync mode)");
            } else {
                // Dispatch to queue
                SendPaymentReminderJob::dispatch($days);
                $this->info("  ✓ Job dispatched to queue");
            }
        }

        $this->newLine();
        $this->info('Payment reminder process completed!');

        return Command::SUCCESS;
    }
}
