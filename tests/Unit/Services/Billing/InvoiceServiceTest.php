<?php

namespace Tests\Unit\Services\Billing;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Invoice;
use App\Models\BillingLog;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\DebtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Carbon\Carbon;
use Mockery;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected InvoiceService $invoiceService;
    protected DebtService $debtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->debtService = Mockery::mock(DebtService::class);
        $this->debtService->shouldReceive('addDebt')->andReturn(null);
        $this->debtService->shouldReceive('reduceDebt')->andReturn(null);

        $this->invoiceService = new InvoiceService($this->debtService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ================================================================
    // GENERATE INVOICE TESTS
    // ================================================================

    /** @test */
    public function it_generates_invoice_for_active_customer()
    {
        // Arrange
        $package = Package::factory()->create([
            'name' => 'Paket 10 Mbps',
            'price' => 150000,
        ]);

        $customer = Customer::factory()->create([
            'status' => 'active',
            'package_id' => $package->id,
        ]);

        // Act
        $invoice = $this->invoiceService->generateInvoiceForCustomer(
            $customer,
            month: 1,
            year: 2024
        );

        // Assert
        $this->assertNotNull($invoice);
        $this->assertEquals($customer->id, $invoice->customer_id);
        $this->assertEquals(1, $invoice->period_month);
        $this->assertEquals(2024, $invoice->period_year);
        $this->assertEquals(150000, $invoice->total_amount);
        $this->assertEquals('pending', $invoice->status);
        $this->assertStringStartsWith('INV202401', $invoice->invoice_number);
    }

    /** @test */
    public function it_calculates_invoice_with_additional_charges_and_discount()
    {
        // Arrange
        $package = Package::factory()->create(['price' => 200000]);

        $customer = Customer::factory()->create([
            'status' => 'active',
            'package_id' => $package->id,
            'additional_charges' => 25000, // PPn atau biaya tambahan
            'discount' => 10000, // Diskon
        ]);

        // Act
        $invoice = $this->invoiceService->generateInvoiceForCustomer($customer, 1, 2024);

        // Assert
        // Total = 200000 + 25000 - 10000 = 215000
        $this->assertEquals(215000, $invoice->total_amount);
        $this->assertEquals(215000, $invoice->remaining_amount);
        $this->assertEquals(0, $invoice->paid_amount);
    }

    /** @test */
    public function it_skips_if_invoice_already_exists()
    {
        // Arrange
        $package = Package::factory()->create(['price' => 150000]);
        $customer = Customer::factory()->create([
            'status' => 'active',
            'package_id' => $package->id,
        ]);

        // Create existing invoice
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 1,
            'period_year' => 2024,
        ]);

        // Act
        $invoice = $this->invoiceService->generateInvoiceForCustomer($customer, 1, 2024);

        // Assert
        $this->assertNull($invoice);
        $this->assertEquals(1, Invoice::where('customer_id', $customer->id)->count());
    }

    /** @test */
    public function it_skips_customer_without_package()
    {
        // Arrange
        $customer = Customer::factory()->create([
            'status' => 'active',
            'package_id' => null,
        ]);

        // Act
        $invoice = $this->invoiceService->generateInvoiceForCustomer($customer, 1, 2024);

        // Assert
        $this->assertNull($invoice);
    }

    /** @test */
    public function it_generates_unique_invoice_numbers()
    {
        // Arrange
        $package = Package::factory()->create(['price' => 150000]);
        $customer1 = Customer::factory()->create(['status' => 'active', 'package_id' => $package->id]);
        $customer2 = Customer::factory()->create(['status' => 'active', 'package_id' => $package->id]);

        // Act
        $invoice1 = $this->invoiceService->generateInvoiceForCustomer($customer1, 1, 2024);
        $invoice2 = $this->invoiceService->generateInvoiceForCustomer($customer2, 1, 2024);

        // Assert
        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
        $this->assertEquals('INV2024010001', $invoice1->invoice_number);
        $this->assertEquals('INV2024010002', $invoice2->invoice_number);
    }

    /** @test */
    public function it_generates_monthly_invoices_for_all_active_customers()
    {
        // Arrange
        $package = Package::factory()->create(['price' => 150000]);

        $activeCustomers = Customer::factory()->count(5)->create([
            'status' => 'active',
            'package_id' => $package->id,
        ]);

        $inactiveCustomer = Customer::factory()->create([
            'status' => 'inactive',
            'package_id' => $package->id,
        ]);

        // Act
        $result = $this->invoiceService->generateMonthlyInvoices(1, 2024);

        // Assert
        $this->assertEquals(5, $result['generated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(5, Invoice::count());
    }

    // ================================================================
    // UPDATE OVERDUE STATUS TESTS
    // ================================================================

    /** @test */
    public function it_marks_invoices_as_overdue_after_grace_period()
    {
        // Arrange
        $customer = Customer::factory()->create();

        // Invoice that should be overdue (past due date + grace period)
        $overdueInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'due_date' => now()->subDays(15), // 15 days ago
        ]);

        // Invoice that should NOT be overdue (within grace period)
        $pendingInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'due_date' => now()->subDays(3), // 3 days ago (within 7 day grace)
        ]);

        // Act
        config(['billing.grace_days' => 7]);
        $result = $this->invoiceService->updateOverdueStatus();

        // Assert
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals('overdue', $overdueInvoice->fresh()->status);
        $this->assertEquals('pending', $pendingInvoice->fresh()->status);
    }

    // ================================================================
    // MARK AS PAID TESTS
    // ================================================================

    /** @test */
    public function it_marks_invoice_as_paid()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 150000]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);

        // Act
        $result = $this->invoiceService->markAsPaid($invoice, 'Manual payment');

        // Assert
        $this->assertEquals('paid', $result->status);
        $this->assertEquals(150000, $result->paid_amount);
        $this->assertEquals(0, $result->remaining_amount);
        $this->assertNotNull($result->paid_at);
        $this->assertEquals('Manual payment', $result->notes);
    }

    // ================================================================
    // CANCEL INVOICE TESTS
    // ================================================================

    /** @test */
    public function it_cancels_pending_invoice()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'remaining_amount' => 150000,
        ]);

        // Act
        $result = $this->invoiceService->cancelInvoice($invoice, 'Customer requested');

        // Assert
        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals('Customer requested', $result->notes);
    }

    /** @test */
    public function it_throws_exception_when_cancelling_non_pending_invoice()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'paid',
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hanya invoice pending yang dapat dibatalkan');

        $this->invoiceService->cancelInvoice($invoice, 'Test');
    }

    // ================================================================
    // STATISTICS TESTS
    // ================================================================

    /** @test */
    public function it_calculates_invoice_statistics()
    {
        // Arrange
        $customer = Customer::factory()->create();

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 1,
            'period_year' => 2024,
            'total_amount' => 150000,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 1,
            'period_year' => 2024,
            'total_amount' => 200000,
            'paid_amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'partial',
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'period_month' => 1,
            'period_year' => 2024,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);

        // Act
        $stats = $this->invoiceService->getStatistics(1, 2024);

        // Assert
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['paid']);
        $this->assertEquals(1, $stats['partial']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(500000, $stats['total_billed']);
        $this->assertEquals(250000, $stats['total_paid']);
        $this->assertEquals(250000, $stats['total_outstanding']);
    }

    // ================================================================
    // HELPER METHODS TESTS
    // ================================================================

    /** @test */
    public function it_gets_overdue_invoices_for_customer()
    {
        // Arrange
        $customer = Customer::factory()->create();

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'period_month' => 10,
            'period_year' => 2023,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'period_month' => 11,
            'period_year' => 2023,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'pending', // Not overdue
        ]);

        // Act
        $overdueInvoices = $this->invoiceService->getOverdueInvoices($customer);

        // Assert
        $this->assertCount(2, $overdueInvoices);
        // Check ordered by period
        $this->assertEquals(10, $overdueInvoices->first()->period_month);
    }

    /** @test */
    public function it_counts_unpaid_months_for_customer()
    {
        // Arrange
        $customer = Customer::factory()->create();

        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'pending']);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'partial']);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'overdue']);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => 'paid']); // Not counted

        // Act
        $count = $this->invoiceService->getUnpaidMonthsCount($customer);

        // Assert
        $this->assertEquals(3, $count);
    }
}
