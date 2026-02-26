<?php

namespace Tests\Unit\Services\Collector;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CollectionLog;
use App\Models\User;
use App\Services\Billing\DebtIsolationService;
use App\Services\Collector\CollectorService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CollectorService $service;
    protected DebtIsolationService $debtServiceMock;
    protected NotificationService $notificationMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->debtServiceMock = $this->createMock(DebtIsolationService::class);
        $this->notificationMock = $this->createMock(NotificationService::class);

        $this->service = new CollectorService(
            $this->debtServiceMock,
            $this->notificationMock
        );
    }

    // ================================================================
    // DASHBOARD STATS
    // ================================================================

    public function test_get_dashboard_stats_returns_correct_structure(): void
    {
        $collector = User::factory()->collector()->create();
        Customer::factory()->count(3)->create(['collector_id' => $collector->id]);

        $stats = $this->service->getDashboardStats($collector, 'today');

        $this->assertArrayHasKey('customers', $stats);
        $this->assertArrayHasKey('revenue', $stats);
        $this->assertArrayHasKey('collection', $stats);
        $this->assertArrayHasKey('settlement', $stats);
        $this->assertEquals(3, $stats['customers']['total']);
    }

    public function test_get_dashboard_stats_counts_paid_this_month(): void
    {
        $collector = User::factory()->collector()->create();
        $customer1 = Customer::factory()->create(['collector_id' => $collector->id]);
        $customer2 = Customer::factory()->create(['collector_id' => $collector->id]);

        Invoice::factory()->paid()->create([
            'customer_id' => $customer1->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer2->id,
            'period_month' => now()->month,
            'period_year' => now()->year,
            'status' => 'pending',
        ]);

        $stats = $this->service->getDashboardStats($collector, 'this_month');

        $this->assertEquals(1, $stats['customers']['paid_this_month']);
        $this->assertEquals(1, $stats['customers']['unpaid_this_month']);
    }

    // ================================================================
    // OVERDUE CUSTOMERS
    // ================================================================

    public function test_get_overdue_customers_returns_assigned_only(): void
    {
        $collector = User::factory()->collector()->create();
        $assigned = Customer::factory()->create([
            'collector_id' => $collector->id,
            'total_debt' => 300000,
        ]);
        $other = Customer::factory()->create(['total_debt' => 500000]);

        $result = $this->service->getOverdueCustomers($collector);

        $customerIds = $result->pluck('id')->toArray();
        $this->assertContains($assigned->id, $customerIds);
        $this->assertNotContains($other->id, $customerIds);
    }

    public function test_get_overdue_customers_search_filter(): void
    {
        $collector = User::factory()->collector()->create();
        Customer::factory()->create([
            'collector_id' => $collector->id,
            'name' => 'Ahmad Santoso',
        ]);
        Customer::factory()->create([
            'collector_id' => $collector->id,
            'name' => 'Budi Prasetyo',
        ]);

        $result = $this->service->getOverdueCustomers($collector, search: 'Ahmad');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Ahmad Santoso', $result->first()->name);
    }

    public function test_get_overdue_customers_status_filter(): void
    {
        $collector = User::factory()->collector()->create();
        Customer::factory()->create([
            'collector_id' => $collector->id,
            'status' => 'active',
        ]);
        Customer::factory()->isolated()->create([
            'collector_id' => $collector->id,
        ]);

        $result = $this->service->getOverdueCustomers($collector, status: 'isolated');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('isolated', $result->first()->status);
    }

    // ================================================================
    // PROCESS CASH PAYMENT
    // ================================================================

    public function test_process_cash_payment_for_assigned_customer(): void
    {
        $collector = User::factory()->collector()->create();
        $customer = Customer::factory()->create([
            'collector_id' => $collector->id,
            'total_debt' => 200000,
        ]);
        $this->actingAs($collector);

        $paymentResult = [
            'success' => true,
            'payment' => Payment::factory()->create(['customer_id' => $customer->id, 'amount' => 200000]),
            'previous_debt' => 200000,
            'new_debt' => 0,
            'access_opened' => false,
        ];

        $this->debtServiceMock
            ->method('processPayment')
            ->willReturn($paymentResult);
        $this->notificationMock
            ->method('sendPaymentConfirmation')
            ->willReturn(['success' => true]);

        $result = $this->service->processCashPayment($collector, $customer, 200000);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('collection_logs', [
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'action_type' => 'payment_cash',
        ]);
    }

    public function test_process_cash_payment_rejects_unassigned_customer(): void
    {
        $collector = User::factory()->collector()->create();
        $customer = Customer::factory()->create(); // Not assigned to collector

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Anda tidak memiliki akses ke pelanggan ini');

        $this->service->processCashPayment($collector, $customer, 200000);
    }

    // ================================================================
    // LOG VISIT
    // ================================================================

    public function test_log_visit_creates_collection_log(): void
    {
        $collector = User::factory()->collector()->create();
        $customer = Customer::factory()->create(['collector_id' => $collector->id]);

        $log = $this->service->logVisit($collector, $customer, 'visit', 'Rumah kosong');

        $this->assertDatabaseHas('collection_logs', [
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'action_type' => 'visit',
        ]);
    }

    public function test_log_visit_rejects_unassigned_customer(): void
    {
        $collector = User::factory()->collector()->create();
        $customer = Customer::factory()->create();

        $this->expectException(\Exception::class);

        $this->service->logVisit($collector, $customer, 'visit');
    }

    // ================================================================
    // CALCULATE SETTLEMENT
    // ================================================================

    public function test_calculate_final_settlement(): void
    {
        $collector = User::factory()->collector()->create(['commission_rate' => 5]);

        // Use a wide date range to avoid timezone issues with SQLite date storage
        $startDate = Carbon::create(2024, 6, 1, 0, 0, 0, 'UTC');
        $endDate = Carbon::create(2024, 6, 30, 23, 59, 59, 'UTC');

        // Create cash payments
        Payment::factory()->cash()->create([
            'collector_id' => $collector->id,
            'amount' => 500000,
            'created_at' => Carbon::create(2024, 6, 15, 10, 0, 0, 'UTC'),
        ]);

        // Create approved expense
        Expense::factory()->approved()->create([
            'user_id' => $collector->id,
            'amount' => 50000,
            'expense_date' => '2024-06-15',
        ]);

        $result = $this->service->calculateFinalSettlement($collector, $startDate, $endDate);

        $this->assertArrayHasKey('must_settle', $result);
        $this->assertArrayHasKey('cash_collection', $result);
        $this->assertEquals(500000, $result['cash_collection']);
        $this->assertEquals(50000, $result['approved_expense']);
    }

    // ================================================================
    // DAILY SUMMARY
    // ================================================================

    public function test_get_daily_summary(): void
    {
        $collector = User::factory()->collector()->create();

        Payment::factory()->cash()->create([
            'collector_id' => $collector->id,
            'amount' => 300000,
        ]);
        Expense::factory()->create([
            'user_id' => $collector->id,
            'amount' => 20000,
            'expense_date' => today(),
        ]);

        $summary = $this->service->getDailySummary($collector);

        $this->assertEquals(today()->toDateString(), $summary['date']);
        $this->assertEquals(1, $summary['payments']['count']);
        $this->assertEquals(300000, $summary['payments']['total']);
        $this->assertEquals(1, $summary['expenses']['count']);
    }
}
