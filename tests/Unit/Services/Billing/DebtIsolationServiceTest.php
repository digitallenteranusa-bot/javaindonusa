<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Setting;
use App\Jobs\IsolateCustomerJob;
use App\Services\Billing\DebtIsolationService;
use App\Services\Billing\DebtService;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DebtIsolationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DebtIsolationService $service;
    protected MikrotikService $mikrotikMock;
    protected NotificationService $notificationMock;
    protected DebtService $debtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mikrotikMock = $this->createMock(MikrotikService::class);
        $this->notificationMock = $this->createMock(NotificationService::class);
        $this->debtService = new DebtService();

        $this->service = new DebtIsolationService(
            $this->mikrotikMock,
            $this->notificationMock,
            $this->debtService
        );

        $this->actingAsAdmin();
    }

    // ================================================================
    // ADD MONTHLY DEBT FOR CUSTOMER
    // ================================================================

    public function test_add_monthly_debt_creates_invoice_and_updates_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 0]);
        $month = now()->month;
        $year = now()->year;

        $result = $this->service->addMonthlyDebtForCustomer($customer, $month, $year);

        $this->assertTrue($result['added']);
        $this->assertNotNull($result['invoice_id']);
        $customer->refresh();
        $this->assertGreaterThan(0, $customer->total_debt);
    }

    public function test_add_monthly_debt_skips_existing_invoice(): void
    {
        $customer = Customer::factory()->create();
        $month = now()->month;
        $year = now()->year;

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => $month,
            'period_year' => $year,
            'status' => 'pending',
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, $month, $year);

        $this->assertFalse($result['added']);
        $this->assertEquals('invoice_exists', $result['reason']);
    }

    public function test_add_monthly_debt_skips_already_paid(): void
    {
        $customer = Customer::factory()->create();
        $month = now()->month;
        $year = now()->year;

        Invoice::factory()->paid()->create([
            'customer_id' => $customer->id,
            'period_month' => $month,
            'period_year' => $year,
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, $month, $year);

        $this->assertFalse($result['added']);
        $this->assertEquals('already_paid', $result['reason']);
    }

    public function test_add_monthly_debt_applies_nominal_discount(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 0,
            'discount_type' => 'nominal',
            'discount_value' => 50000,
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, now()->month, now()->year);

        $this->assertTrue($result['added']);
        $packagePrice = $customer->package->price;
        $this->assertEquals($packagePrice - 50000, $result['amount']);
    }

    public function test_add_monthly_debt_applies_ppn_for_taxed_customer(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 0,
            'is_taxed' => true,
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, now()->month, now()->year);

        $this->assertTrue($result['added']);
        $packagePrice = $customer->package->price;
        $expectedAmount = $packagePrice + round($packagePrice * 0.11, 2);
        $this->assertEquals($expectedAmount, $result['amount']);
    }

    public function test_add_monthly_debt_auto_applies_credit(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 0,
            'credit_balance' => 50000,
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, now()->month, now()->year);

        $this->assertTrue($result['added']);
        $this->assertGreaterThan(0, $result['credit_used']);
    }

    public function test_add_monthly_debt_skips_if_billing_not_started(): void
    {
        $customer = Customer::factory()->create([
            'billing_start_date' => now()->addMonth(),
        ]);

        $result = $this->service->addMonthlyDebtForCustomer($customer, now()->month, now()->year);

        $this->assertFalse($result['added']);
        $this->assertEquals('billing_not_started', $result['reason']);
    }

    // ================================================================
    // SHOULD ISOLATE CUSTOMER
    // ================================================================

    public function test_should_isolate_customer_with_consecutive_overdue(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 600000,
            'payment_behavior' => 'regular',
        ]);

        // Create 3 consecutive overdue invoices with past due dates
        for ($i = 3; $i >= 1; $i--) {
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'period_month' => now()->subMonths($i)->month,
                'period_year' => now()->subMonths($i)->year,
                'due_date' => now()->subMonths($i)->day(20),
                'status' => 'overdue',
                'total_amount' => 200000,
                'remaining_amount' => 200000,
            ]);
        }

        $customer->load('invoices');

        $result = $this->service->shouldIsolateCustomer($customer, 3, 7);

        $this->assertTrue($result['isolate']);
    }

    public function test_should_not_isolate_rapel_customer_within_tolerance(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 400000,
            'payment_behavior' => 'rapel',
            'is_rapel' => true,
            'rapel_months' => 3,
        ]);

        // 2 unpaid invoices - within rapel tolerance
        for ($i = 2; $i >= 1; $i--) {
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'period_month' => now()->subMonths($i)->month,
                'period_year' => now()->subMonths($i)->year,
                'status' => 'pending',
                'total_amount' => 200000,
                'remaining_amount' => 200000,
            ]);
        }

        $customer->load('invoices');

        $result = $this->service->shouldIsolateCustomer($customer);

        $this->assertFalse($result['isolate']);
        $this->assertEquals('rapel_customer', $result['reason']);
    }

    public function test_should_not_isolate_with_recent_payment(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 300000,
            'last_payment_date' => now()->subDays(5),
        ]);

        // Create overdue invoices
        for ($i = 3; $i >= 1; $i--) {
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'period_month' => now()->subMonths($i)->month,
                'period_year' => now()->subMonths($i)->year,
                'due_date' => now()->subMonths($i)->day(20),
                'status' => 'overdue',
                'total_amount' => 100000,
                'remaining_amount' => 100000,
            ]);
        }

        $customer->load('invoices');

        $result = $this->service->shouldIsolateCustomer($customer);

        $this->assertFalse($result['isolate']);
        $this->assertEquals('recent_payment', $result['reason']);
    }

    public function test_should_not_isolate_with_insufficient_overdue(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 200000,
            'payment_behavior' => 'regular',
        ]);

        // Only 1 overdue invoice
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => now()->subMonth()->month,
            'period_year' => now()->subMonth()->year,
            'due_date' => now()->subMonth()->day(20),
            'status' => 'overdue',
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $customer->load('invoices');

        $result = $this->service->shouldIsolateCustomer($customer, 3, 7);

        $this->assertFalse($result['isolate']);
        $this->assertEquals('not_overdue_enough', $result['reason']);
    }

    // ================================================================
    // CHECK AND PROCESS ISOLATION
    // ================================================================

    public function test_check_and_process_isolation_dispatches_jobs(): void
    {
        Queue::fake();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 600000,
            'payment_behavior' => 'regular',
        ]);

        // Create 3+ consecutive overdue invoices
        for ($i = 4; $i >= 1; $i--) {
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'period_month' => now()->subMonths($i)->month,
                'period_year' => now()->subMonths($i)->year,
                'due_date' => now()->subMonths($i)->day(20),
                'status' => 'overdue',
                'total_amount' => 150000,
                'remaining_amount' => 150000,
            ]);
        }

        $results = $this->service->checkAndProcessIsolation();

        $this->assertGreaterThanOrEqual(1, $results['checked']);
        if ($results['isolated'] > 0) {
            Queue::assertPushed(IsolateCustomerJob::class);
        }
    }

    // ================================================================
    // CHECK AND OPEN ACCESS
    // ================================================================

    public function test_check_and_open_access_skips_non_isolated(): void
    {
        $customer = Customer::factory()->create(['status' => 'active']);

        $result = $this->service->checkAndOpenAccess($customer);

        $this->assertFalse($result);
    }

    public function test_check_and_open_access_opens_when_no_overdue(): void
    {
        $router = Router::factory()->create();
        $customer = Customer::factory()->isolated()->create(['total_debt' => 0, 'router_id' => $router->id]);

        $this->mikrotikMock->method('connect')->willReturn(true);
        $this->mikrotikMock->method('removeFromAddressList')->willReturn(['success' => true]);
        $this->mikrotikMock->method('changePPPoEProfile')->willReturn(['success' => true]);
        $this->notificationMock->method('sendAccessOpenedNotice')->willReturn(['success' => true]);

        $result = $this->service->checkAndOpenAccess($customer);

        $this->assertTrue($result);
        $customer->refresh();
        $this->assertEquals('active', $customer->status);
    }

    // ================================================================
    // PROCESS PAYMENT
    // ================================================================

    public function test_process_payment_allocates_fifo(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 300000]);

        // Create 2 invoices, oldest first
        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 1,
            'period_year' => 2024,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'overdue',
        ]);
        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 2,
            'period_year' => 2024,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);

        $this->mikrotikMock->method('connect')->willReturn(true);

        $result = $this->service->processPayment($customer, 200000);

        $this->assertTrue($result['success']);

        $invoice1->refresh();
        $invoice2->refresh();

        // First invoice fully paid
        $this->assertEquals('paid', $invoice1->status);
        $this->assertEquals(150000, $invoice1->paid_amount);

        // Second invoice partially paid
        $this->assertEquals('partial', $invoice2->status);
        $this->assertEquals(50000, $invoice2->paid_amount);
    }
}
