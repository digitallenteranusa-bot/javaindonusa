<?php

namespace Tests\Feature\Admin;

use App\Jobs\SuspendCustomerJob;
use App\Jobs\UnsuspendCustomerJob;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->actingAsAdmin();
    }

    public function test_can_suspend_active_customer(): void
    {
        Bus::fake();

        $customer = Customer::factory()->active()->create();

        $response = $this->post(route('admin.customers.suspend', $customer), [
            'reason' => 'Pindah rumah sementara',
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Bus::assertDispatched(SuspendCustomerJob::class, function ($job) use ($customer) {
            return $job->customerId === $customer->id;
        });
    }

    public function test_cannot_suspend_non_active_customer(): void
    {
        Bus::fake();

        $customer = Customer::factory()->isolated()->create();

        $response = $this->post(route('admin.customers.suspend', $customer), [
            'reason' => 'Pindah rumah',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        Bus::assertNotDispatched(SuspendCustomerJob::class);
    }

    public function test_cannot_suspend_without_reason(): void
    {
        Bus::fake();

        $customer = Customer::factory()->active()->create();

        $response = $this->post(route('admin.customers.suspend', $customer), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');

        Bus::assertNotDispatched(SuspendCustomerJob::class);
    }

    public function test_can_unsuspend_suspended_customer(): void
    {
        Bus::fake();

        $customer = Customer::factory()->create([
            'status' => 'suspended',
            'suspension_start_date' => now()->subMonth(),
            'suspension_reason' => 'Pindah rumah',
        ]);

        $response = $this->post(route('admin.customers.unsuspend', $customer));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Bus::assertDispatched(UnsuspendCustomerJob::class, function ($job) use ($customer) {
            return $job->customerId === $customer->id;
        });
    }

    public function test_cannot_unsuspend_non_suspended_customer(): void
    {
        Bus::fake();

        $customer = Customer::factory()->active()->create();

        $response = $this->post(route('admin.customers.unsuspend', $customer));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        Bus::assertNotDispatched(UnsuspendCustomerJob::class);
    }

    public function test_suspend_job_updates_customer_status(): void
    {
        $customer = Customer::factory()->active()->create();

        // Mock MikrotikService and NotificationService
        $mikrotikService = $this->mock(\App\Services\Mikrotik\MikrotikService::class);
        $mikrotikService->shouldReceive('isolateCustomer')
            ->once()
            ->andReturn(['success' => true]);

        $notificationService = $this->mock(\App\Services\Notification\NotificationService::class);
        $notificationService->shouldReceive('sendWhatsApp')
            ->once()
            ->andReturn(['success' => true]);

        $job = new SuspendCustomerJob($customer->id, 'Renovasi rumah', now()->addMonth()->toDateString());
        $job->handle($mikrotikService, $notificationService);

        $customer->refresh();
        $this->assertEquals('suspended', $customer->status);
        $this->assertNotNull($customer->suspension_start_date);
        $this->assertEquals('Renovasi rumah', $customer->suspension_reason);
    }

    public function test_unsuspend_job_updates_customer_status(): void
    {
        $customer = Customer::factory()->create([
            'status' => 'suspended',
            'suspension_start_date' => now()->subMonth(),
            'suspension_reason' => 'Pindah rumah',
        ]);

        $mikrotikService = $this->mock(\App\Services\Mikrotik\MikrotikService::class);
        $mikrotikService->shouldReceive('reopenCustomer')
            ->once()
            ->andReturn(['success' => true]);

        $notificationService = $this->mock(\App\Services\Notification\NotificationService::class);
        $notificationService->shouldReceive('sendWhatsApp')
            ->once()
            ->andReturn(['success' => true]);

        $job = new UnsuspendCustomerJob($customer->id);
        $job->handle($mikrotikService, $notificationService);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
        $this->assertNull($customer->suspension_start_date);
        $this->assertNull($customer->suspension_end_date);
        $this->assertNull($customer->suspension_reason);
    }

    public function test_suspended_customer_model_helpers(): void
    {
        $customer = Customer::factory()->create([
            'status' => 'suspended',
        ]);

        $this->assertTrue($customer->isSuspended());
        $this->assertFalse($customer->isActive());
        $this->assertFalse($customer->isIsolated());
    }

    public function test_suspended_scope(): void
    {
        Customer::factory()->active()->count(3)->create();
        Customer::factory()->create(['status' => 'suspended']);

        $this->assertCount(1, Customer::suspended()->get());
    }
}
