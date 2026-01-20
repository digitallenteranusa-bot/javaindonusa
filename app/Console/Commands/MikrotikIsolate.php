<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Jobs\IsolateCustomerJob;
use App\Jobs\ReopenCustomerJob;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class MikrotikIsolate extends Command
{
    protected $signature = 'mikrotik:isolate
                            {customer : Customer ID or customer_id (JI-xxxxx)}
                            {--reopen : Reopen access instead of isolate}
                            {--no-notification : Skip sending notification}
                            {--sync : Run synchronously instead of queuing}';

    protected $description = 'Isolate or reopen customer internet access';

    public function handle(
        MikrotikService $mikrotikService,
        NotificationService $notificationService
    ): int {
        $customerQuery = $this->argument('customer');

        // Find customer
        $customer = is_numeric($customerQuery)
            ? Customer::find($customerQuery)
            : Customer::where('customer_id', $customerQuery)->first();

        if (!$customer) {
            $this->error("Customer not found: {$customerQuery}");
            return Command::FAILURE;
        }

        $customer->load(['router', 'package']);

        $this->info("Customer: {$customer->name} ({$customer->customer_id})");
        $this->info("Status: {$customer->status}");
        $this->info("Total Debt: Rp " . number_format($customer->total_debt, 0, ',', '.'));
        $this->info("Router: " . ($customer->router?->name ?? 'Not assigned'));
        $this->newLine();

        $isReopen = $this->option('reopen');
        $sendNotification = !$this->option('no-notification');

        if ($isReopen) {
            return $this->reopenCustomer($customer, $mikrotikService, $notificationService, $sendNotification);
        } else {
            return $this->isolateCustomer($customer, $mikrotikService, $notificationService, $sendNotification);
        }
    }

    protected function isolateCustomer(
        Customer $customer,
        MikrotikService $mikrotikService,
        NotificationService $notificationService,
        bool $sendNotification
    ): int {
        if ($customer->status === 'isolated') {
            $this->warn('Customer is already isolated');
            return Command::SUCCESS;
        }

        if (!$customer->router) {
            $this->error('No router assigned to customer');
            return Command::FAILURE;
        }

        if (!$this->confirm('Isolate this customer?')) {
            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('Isolating customer...');

            try {
                $result = $mikrotikService->isolateCustomer($customer);

                if ($result['success']) {
                    $customer->update([
                        'status' => 'isolated',
                        'isolation_date' => now(),
                        'isolation_reason' => 'Manual isolation via CLI',
                    ]);

                    $this->info('✓ Customer isolated successfully');

                    if ($sendNotification) {
                        $this->info('Sending notification...');
                        $notificationService->sendIsolationNotice($customer);
                        $this->info('✓ Notification sent');
                    }

                    return Command::SUCCESS;
                } else {
                    $this->error('✗ Isolation failed: ' . ($result['message'] ?? 'Unknown error'));
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error('✗ Error: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            IsolateCustomerJob::dispatch($customer->id, $sendNotification);
            $this->info('✓ Isolation job dispatched to queue');
            return Command::SUCCESS;
        }
    }

    protected function reopenCustomer(
        Customer $customer,
        MikrotikService $mikrotikService,
        NotificationService $notificationService,
        bool $sendNotification
    ): int {
        if ($customer->status !== 'isolated') {
            $this->warn('Customer is not isolated');
            return Command::SUCCESS;
        }

        if (!$customer->router) {
            $this->error('No router assigned to customer');
            return Command::FAILURE;
        }

        if (!$this->confirm('Reopen access for this customer?')) {
            return Command::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info('Reopening customer access...');

            try {
                $result = $mikrotikService->reopenCustomer($customer);

                if ($result['success']) {
                    $customer->update([
                        'status' => 'active',
                        'isolation_date' => null,
                        'isolation_reason' => null,
                    ]);

                    $this->info('✓ Customer access reopened successfully');

                    if ($sendNotification) {
                        $this->info('Sending notification...');
                        $notificationService->sendAccessOpenedNotice($customer);
                        $this->info('✓ Notification sent');
                    }

                    return Command::SUCCESS;
                } else {
                    $this->error('✗ Reopen failed: ' . ($result['message'] ?? 'Unknown error'));
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error('✗ Error: ' . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            ReopenCustomerJob::dispatch($customer->id, $sendNotification);
            $this->info('✓ Reopen job dispatched to queue');
            return Command::SUCCESS;
        }
    }
}
