<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ReopenCustomerJob;
use App\Models\Customer;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReopenCustomerJobTest extends TestCase
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

    public function test_reopen_customer_success(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->isolated()->create([
            'router_id' => $router->id,
        ]);

        $this->mikrotikMock
            ->expects($this->once())
            ->method('reopenCustomer')
            ->willReturn(['success' => true]);

        $this->notificationMock
            ->expects($this->once())
            ->method('sendAccessOpenedNotice');

        $job = new ReopenCustomerJob($customer->id, true);
        $job->handle($this->mikrotikMock, $this->notificationMock);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
        $this->assertNull($customer->isolation_date);
    }

    public function test_skip_non_isolated_customer(): void
    {
        $customer = Customer::factory()->active()->create();

        $this->mikrotikMock
            ->expects($this->never())
            ->method('reopenCustomer');

        $job = new ReopenCustomerJob($customer->id);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_skip_not_found(): void
    {
        $this->mikrotikMock
            ->expects($this->never())
            ->method('reopenCustomer');

        $job = new ReopenCustomerJob(999999);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }

    public function test_mikrotik_failure_throws_exception(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->isolated()->create([
            'router_id' => $router->id,
        ]);

        $this->mikrotikMock
            ->method('reopenCustomer')
            ->willThrowException(new \Exception('Connection refused'));

        $this->expectException(\Exception::class);

        $job = new ReopenCustomerJob($customer->id);
        $job->handle($this->mikrotikMock, $this->notificationMock);
    }
}
