<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendPaymentReminderJob;
use App\Jobs\SendNotificationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendPaymentReminderJobTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationMock = $this->createMock(NotificationService::class);
    }

    public function test_dispatches_for_due_invoices(): void
    {
        Queue::fake();
        config(['notification.templates.reminder.enabled' => true]);

        $customer = Customer::factory()->create(['total_debt' => 200000, 'status' => 'active']);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $job = new SendPaymentReminderJob(3);
        $job->handle($this->notificationMock);

        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_skips_terminated_customers(): void
    {
        Queue::fake();
        config(['notification.templates.reminder.enabled' => true]);

        $customer = Customer::factory()->create([
            'status' => 'terminated',
            'total_debt' => 200000,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $job = new SendPaymentReminderJob(3);
        $job->handle($this->notificationMock);

        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_skips_when_disabled(): void
    {
        Queue::fake();
        config(['notification.templates.reminder.enabled' => false]);

        $customer = Customer::factory()->create(['total_debt' => 200000]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        $job = new SendPaymentReminderJob(3);
        $job->handle($this->notificationMock);

        Queue::assertNotPushed(SendNotificationJob::class);
    }
}
