<?php

namespace Tests\Unit\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Customer\CustomerPortalService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerPortalService $service;
    protected NotificationService $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationMock = $this->createMock(NotificationService::class);
        $this->service = new CustomerPortalService($this->notificationMock);
    }

    // ================================================================
    // REQUEST LOGIN
    // ================================================================

    public function test_request_login_registered_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        $this->notificationMock
            ->method('sendWhatsApp')
            ->willReturn(['success' => true]);

        $result = $this->service->requestLogin('081234567890');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('OTP', $result['message']);
        $this->assertDatabaseHas('customer_tokens', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_request_login_unregistered_phone(): void
    {
        $result = $this->service->requestLogin('089999999999');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak terdaftar', $result['message']);
    }

    public function test_request_login_fails_when_whatsapp_fails(): void
    {
        Customer::factory()->create(['phone' => '081234567890']);

        $this->notificationMock
            ->method('sendWhatsApp')
            ->willReturn(['success' => false, 'message' => 'Service unavailable']);

        $result = $this->service->requestLogin('081234567890');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Gagal mengirim OTP', $result['message']);
    }

    // ================================================================
    // VERIFY OTP
    // ================================================================

    public function test_verify_otp_valid(): void
    {
        $customer = Customer::factory()->create(['phone' => '081234567890']);
        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => 'test-token-123',
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(5),
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->verifyOTP('081234567890', '123456');

        $this->assertTrue($result['success']);
        $this->assertEquals('test-token-123', $result['token']);
        $this->assertEquals($customer->id, $result['customer']->id);
    }

    public function test_verify_otp_expired(): void
    {
        $customer = Customer::factory()->create(['phone' => '081234567890']);
        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => 'test-token-123',
            'otp_code' => '123456',
            'otp_expires_at' => now()->subMinutes(1),
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->verifyOTP('081234567890', '123456');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak valid', $result['message']);
    }

    public function test_verify_otp_invalid_code(): void
    {
        $customer = Customer::factory()->create(['phone' => '081234567890']);
        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => 'test-token-123',
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(5),
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->verifyOTP('081234567890', '999999');

        $this->assertFalse($result['success']);
    }

    // ================================================================
    // LOGOUT
    // ================================================================

    public function test_logout_deletes_token(): void
    {
        $customer = Customer::factory()->create();
        CustomerToken::create([
            'customer_id' => $customer->id,
            'token' => 'logout-test-token',
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->logout('logout-test-token');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('customer_tokens', ['token' => 'logout-test-token']);
    }

    // ================================================================
    // DASHBOARD DATA
    // ================================================================

    public function test_get_dashboard_data_returns_correct_structure(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 300000]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'status' => 'pending',
        ]);

        $data = $this->service->getDashboardData($customer);

        $this->assertArrayHasKey('customer', $data);
        $this->assertArrayHasKey('billing', $data);
        $this->assertArrayHasKey('invoices', $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertArrayHasKey('isp_info', $data);
        $this->assertEquals(300000, $data['billing']['total_debt']);
    }
}
