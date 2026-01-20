<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Jobs\SendBulkNotificationJob;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendOverdueNotices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:send-overdue
                            {--days=* : Days after due date to send notices (default: 1,3,7)}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     */
    protected $description = 'Send overdue payment notices to customers with past due invoices';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $daysOption = $this->option('days');

        // Default: 1, 3, 7 days after due date
        $overdueDays = !empty($daysOption)
            ? array_map('intval', $daysOption)
            : config('notification.templates.overdue.days_after', [1, 3, 7]);

        $this->info('Starting overdue notice process...');
        $this->newLine();

        foreach ($overdueDays as $days) {
            $targetDate = Carbon::now()->subDays($days);

            // Find invoices that were due on target date and still unpaid
            $invoices = Invoice::whereDate('due_date', $targetDate->toDateString())
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->where('remaining_amount', '>', 0)
                ->with('customer')
                ->get();

            $customerIds = $invoices->pluck('customer_id')->unique()->toArray();

            $this->info("Processing overdue notices ({$days} day(s) past due)...");
            $this->info("  Found {$invoices->count()} overdue invoices ({$customerIds} customers)");

            if (empty($customerIds)) {
                $this->info("  ✓ No customers to notify");
                continue;
            }

            if ($this->option('sync')) {
                // Run synchronously
                $results = ['sent' => 0, 'failed' => 0];

                $customers = Customer::whereIn('id', $customerIds)->get();

                $bar = $this->output->createProgressBar($customers->count());
                $bar->start();

                foreach ($customers as $customer) {
                    $result = $notificationService->sendOverdueNotice($customer);

                    if ($result['success']) {
                        $results['sent']++;
                    } else {
                        $results['failed']++;
                    }

                    $bar->advance();
                    usleep(200000); // Rate limiting
                }

                $bar->finish();
                $this->newLine();
                $this->info("  ✓ Sent: {$results['sent']}, Failed: {$results['failed']}");
            } else {
                // Dispatch to queue
                SendBulkNotificationJob::dispatch('overdue', $customerIds);
                $this->info("  ✓ Job dispatched to queue for " . count($customerIds) . " customers");
            }
        }

        $this->newLine();
        $this->info('Overdue notice process completed!');

        return Command::SUCCESS;
    }
}
