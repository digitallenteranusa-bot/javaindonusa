<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessDailyIsolationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDailyIsolationJobTest extends TestCase
{
    use RefreshDatabase;

    protected MikrotikService $mikrotikMock;
    protected NotificationService $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mikrotikMock = $this->createMock(MikrotikService::class);
        $this->notificationMock = $this->createMock(NotificationService::class);
    }

    public function test_skips_when_auto_isolation_disabled(): void
    {
        config(['mikrotik.auto_isolation.enabled' => false]);

        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new ProcessDailyIsolationJob();
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_isolates_qualifying_customers(): void
    {
        config(['mikrotik.auto_isolation.enabled' => true]);
        config(['mikrotik.auto_isolation.threshold_months' => 2]);
        config(['mikrotik.auto_isolation.grace_period_days' => 7]);
        config(['mikrotik.auto_isolation.recent_payment_days' => 30]);

        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 400000,
            'router_id' => $router->id,
        ]);

        // Old overdue invoices
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(4),
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(3),
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $this->mikrotikMock
            ->method('isolateCustomer')
            ->willReturn(['success' => true]);
        $this->notificationMock
            ->method('sendAsync');

        $job = new ProcessDailyIsolationJob();
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('isolated', $customer->status);
    }

    public function test_skips_rapel_customer(): void
    {
        config(['mikrotik.auto_isolation.enabled' => true]);
        config(['mikrotik.auto_isolation.exclude_rapel' => true]);

        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 400000,
            'is_rapel' => true,
            'rapel_months' => 3,
            'router_id' => $router->id,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(4),
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new ProcessDailyIsolationJob();
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }

    public function test_skips_customer_with_recent_payment(): void
    {
        config(['mikrotik.auto_isolation.enabled' => true]);
        config(['mikrotik.auto_isolation.recent_payment_days' => 30]);

        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 400000,
            'router_id' => $router->id,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(4),
            'total_amount' => 400000,
            'remaining_amount' => 400000,
        ]);

        // Recent payment
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 100000,
            'created_at' => now()->subDays(5),
        ]);

        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new ProcessDailyIsolationJob();
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }

    public function test_handles_individual_customer_errors_gracefully(): void
    {
        config(['mikrotik.auto_isolation.enabled' => true]);
        config(['mikrotik.auto_isolation.threshold_months' => 2]);
        config(['mikrotik.auto_isolation.grace_period_days' => 7]);

        $router = Router::factory()->create();

        // Customer 1 - will fail
        $customer1 = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 400000,
            'router_id' => $router->id,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer1->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(4),
        ]);

        // Customer 2 - will succeed
        $customer2 = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 400000,
            'router_id' => $router->id,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer2->id,
            'status' => 'overdue',
            'due_date' => now()->subMonths(4),
        ]);

        $callCount = 0;
        $this->mikrotikMock
            ->method('isolateCustomer')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('Connection refused');
                }
                return ['success' => true];
            });

        $this->notificationMock->method('sendAsync');

        // Should not throw - handles errors per customer
        $job = new ProcessDailyIsolationJob();
        $job->handle($this->mikrotikMock, $this->notificationMock);

        // At least one should have been attempted
        $this->assertGreaterThanOrEqual(1, $callCount);
    }
}
