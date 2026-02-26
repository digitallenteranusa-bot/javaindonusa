<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Customer;
use App\Models\DebtHistory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\DebtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DebtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DebtService();
        $this->actingAsAdmin();
    }

    // ================================================================
    // ADD DEBT
    // ================================================================

    public function test_add_debt_increases_customer_total_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 0]);

        $history = $this->service->addDebt($customer, 150000);

        $customer->refresh();
        $this->assertEquals(150000, $customer->total_debt);
        $this->assertInstanceOf(DebtHistory::class, $history);
        $this->assertEquals(DebtHistory::TYPE_CHARGE, $history->type);
        $this->assertEquals(0, $history->balance_before);
        $this->assertEquals(150000, $history->balance_after);
    }

    public function test_add_debt_accumulates_on_existing_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 200000]);

        $this->service->addDebt($customer, 150000);

        $customer->refresh();
        $this->assertEquals(350000, $customer->total_debt);
    }

    public function test_add_debt_with_invoice_reference(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 0]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $history = $this->service->addDebt(
            $customer, 150000, 'invoice_added', 'invoice', $invoice->id
        );

        $this->assertEquals($invoice->id, $history->invoice_id);
        $this->assertNotNull($history->reference_number);
    }

    // ================================================================
    // REDUCE DEBT
    // ================================================================

    public function test_reduce_debt_decreases_customer_total_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 300000]);

        $history = $this->service->reduceDebt($customer, 150000);

        $customer->refresh();
        $this->assertEquals(150000, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_PAYMENT, $history->type);
    }

    public function test_reduce_debt_does_not_go_below_zero(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 100000, 'credit_balance' => 0]);

        $this->service->reduceDebt($customer, 150000);

        $customer->refresh();
        $this->assertEquals(0, $customer->total_debt);
    }

    public function test_overpayment_creates_credit(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 100000, 'credit_balance' => 0]);

        $this->service->reduceDebt($customer, 150000);

        $customer->refresh();
        $this->assertEquals(0, $customer->total_debt);
        $this->assertEquals(50000, $customer->credit_balance);
    }

    // ================================================================
    // SPECIALIZED OPERATIONS
    // ================================================================

    public function test_add_late_fee(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 200000]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $history = $this->service->addLateFee($customer, $invoice, 10000);

        $customer->refresh();
        $this->assertEquals(210000, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_LATE_FEE, $history->type);
        $this->assertEquals($invoice->id, $history->invoice_id);
    }

    public function test_add_discount(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 200000]);

        $history = $this->service->addDiscount($customer, 50000, 'Diskon promo');

        $customer->refresh();
        $this->assertEquals(150000, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_DISCOUNT, $history->type);
        $this->assertStringContainsString('Diskon', $history->description);
    }

    public function test_adjust_debt_positive(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 100000]);

        $history = $this->service->adjustDebt($customer, 50000, 'Koreksi tagihan');

        $customer->refresh();
        $this->assertEquals(150000, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_ADJUSTMENT_ADD, $history->type);
    }

    public function test_adjust_debt_negative(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 100000]);

        $history = $this->service->adjustDebt($customer, -30000, 'Koreksi kelebihan');

        $customer->refresh();
        $this->assertEquals(70000, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_ADJUSTMENT_SUBTRACT, $history->type);
    }

    public function test_write_off_debt(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 500000]);

        $history = $this->service->writeOffDebt($customer, 500000, 'Tidak tertagih');

        $customer->refresh();
        $this->assertEquals(0, $customer->total_debt);
        $this->assertEquals(DebtHistory::TYPE_WRITEOFF, $history->type);
    }

    // ================================================================
    // CREDIT OPERATIONS
    // ================================================================

    public function test_add_credit(): void
    {
        $customer = Customer::factory()->create(['credit_balance' => 0]);

        $history = $this->service->addCredit($customer, 50000);

        $customer->refresh();
        $this->assertEquals(50000, $customer->credit_balance);
        $this->assertEquals(DebtHistory::TYPE_CREDIT_ADDED, $history->type);
    }

    public function test_use_credit_applies_to_invoice(): void
    {
        $customer = Customer::factory()->create([
            'total_debt' => 150000,
            'credit_balance' => 50000,
        ]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);

        $history = $this->service->useCredit($customer, $invoice, 50000);

        $customer->refresh();
        $invoice->refresh();
        $this->assertEquals(0, $customer->credit_balance);
        $this->assertEquals(100000, $customer->total_debt);
        $this->assertEquals(50000, $invoice->paid_amount);
        $this->assertEquals(100000, $invoice->remaining_amount);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_use_credit_returns_empty_if_no_credit(): void
    {
        $customer = Customer::factory()->create(['credit_balance' => 0, 'total_debt' => 100000]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        $history = $this->service->useCredit($customer, $invoice, 50000);

        $this->assertNull($history->id);
    }

    // ================================================================
    // QUERY OPERATIONS
    // ================================================================

    public function test_get_debt_summary(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 300000]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'remaining_amount' => 150000,
            'status' => 'pending',
        ]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 150000,
            'remaining_amount' => 150000,
            'status' => 'overdue',
        ]);

        $summary = $this->service->getDebtSummary($customer);

        $this->assertEquals($customer->id, $summary['customer_id']);
        $this->assertEquals(300000, $summary['total_debt']);
        $this->assertEquals(2, $summary['unpaid_invoices_count']);
        $this->assertEquals(300000, $summary['total_from_invoices']);
    }

    // ================================================================
    // RECALCULATE
    // ================================================================

    public function test_recalculate_debt_adjusts_when_out_of_sync(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 500000]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 200000,
            'remaining_amount' => 200000,
            'status' => 'pending',
        ]);

        $result = $this->service->recalculateDebt($customer);

        $customer->refresh();
        $this->assertTrue($result['adjusted']);
        $this->assertEquals(200000, $customer->total_debt);
    }

    public function test_recalculate_debt_no_change_when_synced(): void
    {
        $customer = Customer::factory()->create(['total_debt' => 200000]);
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 200000,
            'remaining_amount' => 200000,
            'status' => 'pending',
        ]);

        $result = $this->service->recalculateDebt($customer);

        $this->assertFalse($result['adjusted']);
    }

    public function test_bulk_recalculate_debt(): void
    {
        $customer1 = Customer::factory()->create(['total_debt' => 999999, 'status' => 'active']);
        Invoice::factory()->create([
            'customer_id' => $customer1->id,
            'total_amount' => 100000,
            'remaining_amount' => 100000,
            'status' => 'pending',
        ]);

        $customer2 = Customer::factory()->create(['total_debt' => 200000, 'status' => 'active']);
        Invoice::factory()->create([
            'customer_id' => $customer2->id,
            'total_amount' => 200000,
            'remaining_amount' => 200000,
            'status' => 'pending',
        ]);

        $results = $this->service->bulkRecalculateDebt(false);

        $this->assertEquals(2, $results['total']);
        $this->assertEquals(1, $results['adjusted']);
        $this->assertEquals(1, $results['unchanged']);
    }
}
