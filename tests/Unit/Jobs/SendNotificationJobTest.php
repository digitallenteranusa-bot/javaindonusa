<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendNotificationJob;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationMock = $this->createMock(NotificationService::class);
    }

    public function test_send_whatsapp_notification(): void
    {
        config(['notification.business_hours.enabled' => false]);

        $this->notificationMock
            ->expects($this->once())
            ->method('sendWhatsApp')
            ->with('081234567890', 'Hello test', [])
            ->willReturn(['success' => true]);

        $job = new SendNotificationJob('whatsapp', '081234567890', 'Hello test');
        $job->handle($this->notificationMock);
    }

    public function test_send_email_notification(): void
    {
        config(['notification.business_hours.enabled' => false]);

        $this->notificationMock
            ->expects($this->once())
            ->method('sendEmail')
            ->willReturn(['success' => true]);

        $job = new SendNotificationJob('email', 'user@example.com', 'Email body', 'Subject');
        $job->handle($this->notificationMock);
    }

    public function test_retry_on_failure(): void
    {
        config(['notification.business_hours.enabled' => false]);

        $this->notificationMock
            ->method('sendWhatsApp')
            ->willReturn(['success' => false, 'message' => 'Service unavailable']);

        $this->expectException(\Exception::class);

        $job = new SendNotificationJob('whatsapp', '081234567890', 'Test');
        $job->handle($this->notificationMock);
    }

    public function test_job_has_correct_properties(): void
    {
        $job = new SendNotificationJob('whatsapp', '081234567890', 'Test');

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    public function test_business_hours_release(): void
    {
        // Configure business hours to make current time outside hours
        config(['notification.business_hours.enabled' => true]);
        config(['notification.business_hours.timezone' => 'UTC']);
        config(['notification.business_hours.start' => '23:58']);
        config(['notification.business_hours.end' => '23:59']);
        config(['notification.business_hours.skip_weekends' => false]);

        // The notification should NOT be called since we're outside business hours
        $this->notificationMock
            ->expects($this->never())
            ->method('sendWhatsApp');

        $job = $this->getMockBuilder(SendNotificationJob::class)
            ->setConstructorArgs(['whatsapp', '081234567890', 'Test'])
            ->onlyMethods(['release'])
            ->getMock();

        $job->expects($this->once())->method('release');

        $job->handle($this->notificationMock);
    }
}
