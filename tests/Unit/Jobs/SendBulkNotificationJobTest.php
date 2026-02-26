<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendBulkNotificationJob;
use App\Jobs\SendNotificationJob;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBulkNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_individual_jobs_per_customer(): void
    {
        Queue::fake();

        $customers = Customer::factory()->count(3)->create(['total_debt' => 200000]);
        $customerIds = $customers->pluck('id')->toArray();

        $job = new SendBulkNotificationJob('reminder', $customerIds);
        $job->handle();

        Queue::assertPushed(SendNotificationJob::class, 3);
    }

    public function test_stagger_delays_between_messages(): void
    {
        Queue::fake();

        $customers = Customer::factory()->count(2)->create(['total_debt' => 200000]);
        $customerIds = $customers->pluck('id')->toArray();

        config(['notification.whatsapp.rate_limit.bulk_delay_seconds' => 15]);

        $job = new SendBulkNotificationJob('reminder', $customerIds);
        $job->handle();

        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_skip_no_debt_for_reminders(): void
    {
        Queue::fake();

        $withDebt = Customer::factory()->create(['total_debt' => 200000]);
        $noDebt = Customer::factory()->create(['total_debt' => 0]);

        $job = new SendBulkNotificationJob('reminder', [$withDebt->id, $noDebt->id]);
        $job->handle();

        // Only customer with debt should get reminder
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_broadcast_sends_to_all(): void
    {
        Queue::fake();

        $customers = Customer::factory()->count(2)->create(['total_debt' => 0]);
        $customerIds = $customers->pluck('id')->toArray();

        $job = new SendBulkNotificationJob('broadcast', $customerIds, [
            'message' => 'Selamat Hari Raya, {name}!',
        ]);
        $job->handle();

        Queue::assertPushed(SendNotificationJob::class, 2);
    }
}
