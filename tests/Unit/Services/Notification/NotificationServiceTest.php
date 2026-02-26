<?php

namespace Tests\Unit\Services\Notification;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Setting;
use App\Jobs\SendNotificationJob;
use App\Services\Notification\Channels\WhatsAppChannel;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;
    protected WhatsAppChannel $whatsappMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->whatsappMock = $this->createMock(WhatsAppChannel::class);
        $this->service = new NotificationService($this->whatsappMock);
    }

    private function enableWhatsApp(): void
    {
        Setting::setValue('notification', 'whatsapp_enabled', '1', 'boolean');
    }

    private function disableWhatsApp(): void
    {
        Setting::setValue('notification', 'whatsapp_enabled', '0', 'boolean');
    }

    // ================================================================
    // SEND WHATSAPP
    // ================================================================

    public function test_send_whatsapp_when_enabled(): void
    {
        $this->enableWhatsApp();

        $this->whatsappMock
            ->expects($this->once())
            ->method('send')
            ->willReturn(['success' => true, 'message' => 'Sent']);

        $result = $this->service->sendWhatsApp('081234567890', 'Test message');

        $this->assertTrue($result['success']);
    }

    public function test_send_whatsapp_returns_false_when_disabled(): void
    {
        $this->disableWhatsApp();

        $result = $this->service->sendWhatsApp('081234567890', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    // ================================================================
    // PHONE NORMALIZATION
    // ================================================================

    public function test_phone_normalization_converts_08_to_62(): void
    {
        $this->enableWhatsApp();

        $this->whatsappMock
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo('6281234567890'),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(['success' => true]);

        $this->service->sendWhatsApp('081234567890', 'Test');
    }

    // ================================================================
    // SEND ASYNC
    // ================================================================

    public function test_send_async_dispatches_job(): void
    {
        Queue::fake();

        $this->service->sendAsync('whatsapp', '081234567890', 'Test async');

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return true;
        });
    }

    // ================================================================
    // BILLING NOTIFICATIONS
    // ================================================================

    public function test_send_isolation_notice(): void
    {
        $this->enableWhatsApp();

        $customer = Customer::factory()->create(['total_debt' => 500000]);

        $this->whatsappMock
            ->method('send')
            ->willReturn(['success' => true]);

        $result = $this->service->sendIsolationNotice($customer);

        $this->assertTrue($result['success']);
    }

    public function test_send_payment_confirmation(): void
    {
        $this->enableWhatsApp();

        $customer = Customer::factory()->create(['total_debt' => 100000]);
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 200000,
        ]);

        $this->whatsappMock
            ->method('send')
            ->willReturn(['success' => true]);

        $result = $this->service->sendPaymentConfirmation($customer, $payment);

        $this->assertTrue($result['success']);
    }

    public function test_send_payment_reminder_skips_no_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 0]);

        $result = $this->service->sendPaymentReminder($customer);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no debt', $result['message']);
    }

    public function test_send_payment_reminder_with_debt(): void
    {
        $this->enableWhatsApp();

        $customer = Customer::factory()->create(['total_debt' => 200000]);

        $this->whatsappMock
            ->method('send')
            ->willReturn(['success' => true]);

        $result = $this->service->sendPaymentReminder($customer, 3);

        $this->assertTrue($result['success']);
    }
}
