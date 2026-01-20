<?php

namespace Tests\Unit\Services\Billing;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Billing\DebtService;
use App\Jobs\ReopenCustomerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use Mockery;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected PaymentService $paymentService;
    protected DebtService $debtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->debtService = Mockery::mock(DebtService::class);
        $this->debtService->shouldReceive('addDebt')->andReturn(null);
        $this->debtService->shouldReceive('reduceDebt')->andReturn(null);

        $this->paymentService = new PaymentService($this->debtService);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ================================================================
    // PROCESS PAYMENT TESTS
    // ================================================================

    /** @test */
    public function it_processes_payment_for_customer()
    {
        // Arrange
        $customer = Customer::factory()->create([
            'status' => 'active',
            'total_debt' => 300000,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
            'period_month' => 1,
            'period_year' => 2024,
        ]);

        // Act
        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: 150000,
            paymentMethod: 'cash'
        );

        // Assert
        $this->assertNotNull($payment);
        $this->assertEquals($customer->id, $payment->customer_id);
        $this->assertEquals(150000, $payment->amount);
        $this->assertEquals('cash', $payment->payment_method);
        $this->assertEquals('success', $payment->status);
        $this->assertStringStartsWith('PAY', $payment->payment_number);
    }

    /** @test */
    public function it_processes_payment_with_collector()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 150000]);
        $collector = User::factory()->create(['role' => 'collector']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);

        // Act
        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: 150000,
            paymentMethod: 'cash',
            collector: $collector
        );

        // Assert
        $this->assertEquals($collector->id, $payment->collector_id);
        $this->assertEquals('collector', $payment->payment_channel);
    }

    /** @test */
    public function it_processes_transfer_payment_with_proof()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 200000]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 200000,
            'paid_amount' => 0,
            'remaining_amount' => 200000,
            'status' => 'pending',
        ]);

        // Act
        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: 200000,
            paymentMethod: 'transfer',
            transferProof: 'uploads/transfer_proof_123.jpg',
            notes: 'Transfer via BCA'
        );

        // Assert
        $this->assertEquals('transfer', $payment->payment_method);
        $this->assertEquals('uploads/transfer_proof_123.jpg', $payment->transfer_proof);
        $this->assertEquals('Transfer via BCA', $payment->notes);
    }

    // ================================================================
    // PAYMENT ALLOCATION TESTS (FIFO)
    // ================================================================

    /** @test */
    public function it_allocates_payment_to_oldest_invoice_first()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 450000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
            'period_month' => 10,
            'period_year' => 2023, // Oldest
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
            'period_month' => 11,
            'period_year' => 2023,
        ]);

        $invoice3 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
            'period_month' => 12,
            'period_year' => 2023, // Newest
        ]);

        // Act - Pay 200000 (should cover invoice1 fully and invoice2 partially)
        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: 200000,
            paymentMethod: 'cash'
        );

        // Assert
        $invoice1->refresh();
        $invoice2->refresh();
        $invoice3->refresh();

        // Invoice 1 should be fully paid
        $this->assertEquals('paid', $invoice1->status);
        $this->assertEquals(150000, $invoice1->paid_amount);
        $this->assertEquals(0, $invoice1->remaining_amount);

        // Invoice 2 should be partially paid (50000)
        $this->assertEquals('partial', $invoice2->status);
        $this->assertEquals(50000, $invoice2->paid_amount);
        $this->assertEquals(100000, $invoice2->remaining_amount);

        // Invoice 3 should be unchanged
        $this->assertEquals('pending', $invoice3->status);
        $this->assertEquals(0, $invoice3->paid_amount);
    }

    /** @test */
    public function it_handles_overpayment_as_general_debt_reduction()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 150000]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        // Act - Pay more than invoice amount
        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: 150000,
            paymentMethod: 'cash'
        );

        // Assert
        $this->assertEquals(100000, $payment->allocated_to_invoice);
        $this->assertEquals(50000, $payment->allocated_to_debt);
    }

    // ================================================================
    // REOPEN CUSTOMER TESTS
    // ================================================================

    /** @test */
    public function it_dispatches_reopen_job_when_isolated_customer_pays_off_debt()
    {
        // Arrange
        $customer = Customer::factory()->create([
            'status' => 'isolated',
            'total_debt' => 150000,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'overdue',
        ]);

        // Act
        $this->paymentService->processPayment(
            customer: $customer,
            amount: 150000,
            paymentMethod: 'cash'
        );

        // Assert - After payment, invoice status changes to paid
        Queue::assertPushed(ReopenCustomerJob::class, function ($job) use ($customer) {
            return $job->customer->id === $customer->id;
        });
    }

    /** @test */
    public function it_does_not_reopen_if_customer_still_has_overdue_invoices()
    {
        // Arrange
        $customer = Customer::factory()->create([
            'status' => 'isolated',
            'total_debt' => 300000,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'overdue',
            'period_month' => 10,
            'period_year' => 2023,
        ]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'overdue', // Second overdue invoice
            'period_month' => 11,
            'period_year' => 2023,
        ]);

        // Act - Pay only one invoice
        $this->paymentService->processPayment(
            customer: $customer,
            amount: 150000,
            paymentMethod: 'cash'
        );

        // Assert - Still has overdue, should not reopen
        Queue::assertNotPushed(ReopenCustomerJob::class);
    }

    // ================================================================
    // CANCEL PAYMENT TESTS
    // ================================================================

    /** @test */
    public function it_cancels_payment_within_24_hours()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 0]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'status' => 'paid',
        ]);

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150000,
            'status' => 'success',
            'created_at' => now()->subHours(2), // 2 hours ago
        ]);

        $payment->invoices()->attach($invoice->id, ['amount' => 150000]);

        // Act
        $result = $this->paymentService->cancelPayment($payment, 'Customer refund request');

        // Assert
        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals('Customer refund request', $result->notes);

        // Check invoice is reversed
        $invoice->refresh();
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(150000, $invoice->remaining_amount);
    }

    /** @test */
    public function it_throws_exception_when_cancelling_after_24_hours()
    {
        // Arrange
        $customer = Customer::factory()->create();

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150000,
            'status' => 'success',
            'created_at' => now()->subHours(25), // 25 hours ago
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pembayaran hanya dapat dibatalkan dalam 24 jam');

        $this->paymentService->cancelPayment($payment, 'Test');
    }

    /** @test */
    public function it_throws_exception_when_cancelling_already_cancelled_payment()
    {
        // Arrange
        $customer = Customer::factory()->create();

        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150000,
            'status' => 'cancelled',
            'created_at' => now(),
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pembayaran sudah dibatalkan');

        $this->paymentService->cancelPayment($payment, 'Test');
    }

    // ================================================================
    // PAYMENT NUMBER GENERATION TESTS
    // ================================================================

    /** @test */
    public function it_generates_unique_payment_numbers()
    {
        // Arrange
        $customer = Customer::factory()->create(['total_debt' => 300000]);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 300000,
            'paid_amount' => 0,
            'remaining_amount' => 300000,
            'status' => 'pending',
        ]);

        // Act
        $payment1 = $this->paymentService->processPayment($customer, 100000, 'cash');
        $payment2 = $this->paymentService->processPayment($customer, 100000, 'cash');

        // Assert
        $this->assertNotEquals($payment1->payment_number, $payment2->payment_number);

        $dateCode = now()->format('Ymd');
        $this->assertEquals("PAY{$dateCode}0001", $payment1->payment_number);
        $this->assertEquals("PAY{$dateCode}0002", $payment2->payment_number);
    }

    // ================================================================
    // STATISTICS TESTS
    // ================================================================

    /** @test */
    public function it_calculates_payment_statistics()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $collector = User::factory()->create(['role' => 'collector']);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150000,
            'payment_method' => 'cash',
            'collector_id' => $collector->id,
            'status' => 'success',
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 200000,
            'payment_method' => 'transfer',
            'collector_id' => null, // Admin payment
            'status' => 'success',
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 100000,
            'status' => 'cancelled', // Should not be counted
            'created_at' => now(),
        ]);

        // Act
        $stats = $this->paymentService->getStatistics(
            now()->toDateString(),
            now()->toDateString()
        );

        // Assert
        $this->assertEquals(2, $stats['total_count']);
        $this->assertEquals(350000, $stats['total_amount']);
        $this->assertEquals(150000, $stats['cash']);
        $this->assertEquals(200000, $stats['transfer']);
        $this->assertEquals(150000, $stats['by_collector']);
        $this->assertEquals(200000, $stats['by_admin']);
    }

    /** @test */
    public function it_calculates_daily_collection_summary_for_collector()
    {
        // Arrange
        $collector = User::factory()->create(['role' => 'collector']);
        $customer = Customer::factory()->create();

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'amount' => 150000,
            'payment_method' => 'cash',
            'status' => 'success',
            'created_at' => now(),
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'amount' => 100000,
            'payment_method' => 'transfer',
            'status' => 'success',
            'created_at' => now(),
        ]);

        // Payment from different collector (should not be counted)
        $otherCollector = User::factory()->create(['role' => 'collector']);
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'collector_id' => $otherCollector->id,
            'amount' => 200000,
            'status' => 'success',
            'created_at' => now(),
        ]);

        // Act
        $summary = $this->paymentService->getDailyCollectionSummary($collector);

        // Assert
        $this->assertEquals(250000, $summary['total']);
        $this->assertEquals(150000, $summary['cash']);
        $this->assertEquals(100000, $summary['transfer']);
        $this->assertEquals(2, $summary['count']);
    }

    /** @test */
    public function it_gets_customer_payments()
    {
        // Arrange
        $customer = Customer::factory()->create();

        Payment::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'status' => 'success',
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'cancelled', // Should not be included
        ]);

        // Act
        $payments = $this->paymentService->getCustomerPayments($customer, 10);

        // Assert
        $this->assertCount(5, $payments);
    }
}
