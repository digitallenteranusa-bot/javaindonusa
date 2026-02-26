<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsolateCustomerJobTest extends TestCase
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

    public function test_isolate_customer_success(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 500000,
            'router_id' => $router->id,
        ]);

        $this->mikrotikMock
            ->expects($this->once())
            ->method('isolateCustomer')
            ->willReturn(['success' => true]);

        $this->notificationMock
            ->expects($this->once())
            ->method('sendIsolationNotice');

        $job = new IsolateCustomerJob($customer->id, true);
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('isolated', $customer->status);
        $this->assertNotNull($customer->isolation_date);
    }

    public function test_skip_already_isolated_customer(): void
    {
        $customer = Customer::factory()->isolated()->create();

        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new IsolateCustomerJob($customer->id);
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('isolated', $customer->status);
    }

    public function test_skip_customer_not_found(): void
    {
        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new IsolateCustomerJob(999999);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_no_notification_when_flag_disabled(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 500000,
            'router_id' => $router->id,
        ]);

        $this->mikrotikMock
            ->method('isolateCustomer')
            ->willReturn(['success' => true]);

        $this->notificationMock
            ->expects($this->never())
            ->method('sendIsolationNotice');

        $job = new IsolateCustomerJob($customer->id, false);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_mikrotik_failure_throws_exception(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 500000,
            'router_id' => $router->id,
        ]);

        $this->mikrotikMock
            ->method('isolateCustomer')
            ->willThrowException(new \Exception('Connection refused'));

        $this->expectException(\Exception::class);

        $job = new IsolateCustomerJob($customer->id);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_skip_customer_that_cannot_be_isolated(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 500000,
            'router_id' => $router->id,
            'is_rapel' => true,
            'rapel_months' => 3,
        ]);

        $this->mikrotikMock
            ->expects($this->never())
            ->method('isolateCustomer');

        $job = new IsolateCustomerJob($customer->id);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }
}
