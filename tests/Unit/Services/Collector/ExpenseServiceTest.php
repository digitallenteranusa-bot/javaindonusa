<?php

namespace Tests\Unit\Services\Collector;

use App\Models\Expense;
use App\Models\User;
use App\Services\Collector\ExpenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExpenseService();
    }

    // ================================================================
    // CREATE EXPENSE
    // ================================================================

    public function test_create_expense(): void
    {
        $collector = User::factory()->collector()->create();

        $expense = $this->service->createExpense(
            $collector, 25000, 'transport', 'Bensin ke area timur'
        );

        $this->assertDatabaseHas('expenses', [
            'user_id' => $collector->id,
            'amount' => 25000,
            'category' => 'transport',
            'status' => 'pending',
        ]);
    }

    public function test_create_expense_with_custom_date(): void
    {
        $collector = User::factory()->collector()->create();
        $date = Carbon::yesterday();

        $expense = $this->service->createExpense(
            $collector, 15000, 'meal', 'Makan siang', null, $date
        );

        $this->assertEquals($date->toDateString(), $expense->expense_date->toDateString());
    }

    // ================================================================
    // APPROVE / REJECT
    // ================================================================

    public function test_approve_expense(): void
    {
        $admin = User::factory()->admin()->create();
        $expense = Expense::factory()->create(['status' => 'pending']);

        $result = $this->service->approveExpense($expense, $admin);

        $this->assertEquals('approved', $result->status);
        $this->assertEquals($admin->id, $result->verified_by);
        $this->assertNotNull($result->verified_at);
    }

    public function test_reject_expense(): void
    {
        $admin = User::factory()->admin()->create();
        $expense = Expense::factory()->create(['status' => 'pending']);

        $result = $this->service->rejectExpense($expense, $admin, 'Tidak ada nota');

        $this->assertEquals('rejected', $result->status);
        $this->assertEquals('Tidak ada nota', $result->rejection_reason);
        $this->assertEquals($admin->id, $result->verified_by);
    }

    // ================================================================
    // SETTLEMENT
    // ================================================================

    public function test_verify_settlement(): void
    {
        $admin = User::factory()->admin()->create();
        $settlement = \App\Models\Settlement::factory()->create([
            'expected_amount' => 500000,
            'actual_amount' => 0,
            'status' => 'pending',
        ]);

        $result = $this->service->verifySettlement($settlement, $admin, 500000);

        $this->assertEquals('settled', $result->status);
        $this->assertEquals(500000, $result->actual_amount);
        $this->assertEquals(0, $result->difference);
    }

    public function test_verify_settlement_with_discrepancy(): void
    {
        $admin = User::factory()->admin()->create();
        $settlement = \App\Models\Settlement::factory()->create([
            'expected_amount' => 500000,
            'actual_amount' => 0,
            'status' => 'pending',
        ]);

        $result = $this->service->verifySettlement($settlement, $admin, 450000);

        $this->assertEquals('discrepancy', $result->status);
        $this->assertEquals(-50000, $result->difference);
    }

    // ================================================================
    // QUERIES
    // ================================================================

    public function test_get_today_expense_total(): void
    {
        $collector = User::factory()->collector()->create();

        Expense::factory()->create([
            'user_id' => $collector->id,
            'amount' => 25000,
            'expense_date' => today(),
            'status' => 'pending',
        ]);
        Expense::factory()->create([
            'user_id' => $collector->id,
            'amount' => 15000,
            'expense_date' => today(),
            'status' => 'approved',
        ]);
        // Rejected should be excluded
        Expense::factory()->rejected()->create([
            'user_id' => $collector->id,
            'amount' => 99000,
            'expense_date' => today(),
        ]);

        $total = $this->service->getTodayExpenseTotal($collector);

        $this->assertEquals(40000, $total);
    }

    public function test_monthly_expense_summary(): void
    {
        $collector = User::factory()->collector()->create();

        Expense::factory()->create([
            'user_id' => $collector->id,
            'amount' => 30000,
            'category' => 'transport',
            'expense_date' => now(),
            'status' => 'approved',
        ]);
        Expense::factory()->create([
            'user_id' => $collector->id,
            'amount' => 20000,
            'category' => 'meal',
            'expense_date' => now(),
            'status' => 'pending',
        ]);

        $summary = $this->service->getMonthlyExpenseSummary($collector);

        $this->assertEquals(50000, $summary['total_expenses']);
        $this->assertEquals(2, $summary['total_count']);
        $this->assertEquals(30000, $summary['approved_total']);
        $this->assertEquals(20000, $summary['pending_total']);
        $this->assertArrayHasKey('by_category', $summary);
    }
}
