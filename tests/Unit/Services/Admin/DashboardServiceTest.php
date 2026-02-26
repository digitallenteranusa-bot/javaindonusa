<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService();
    }

    public function test_get_dashboard_stats_returns_correct_structure(): void
    {
        $stats = $this->service->getDashboardStats('this_month');

        $this->assertArrayHasKey('overview', $stats);
        $this->assertArrayHasKey('revenue', $stats);
        $this->assertArrayHasKey('customers', $stats);
        $this->assertArrayHasKey('invoices', $stats);
        $this->assertArrayHasKey('collectors', $stats);
        $this->assertArrayHasKey('charts', $stats);
        $this->assertArrayHasKey('recent_activities', $stats);
        $this->assertArrayHasKey('alerts', $stats);
    }

    public function test_get_dashboard_stats_counts_customers(): void
    {
        Customer::factory()->count(3)->create(['status' => 'active']);
        Customer::factory()->isolated()->create();

        $stats = $this->service->getDashboardStats();

        $this->assertEquals(4, $stats['overview']['total_customers']);
        $this->assertEquals(3, $stats['overview']['active_customers']);
        $this->assertEquals(1, $stats['overview']['isolated_customers']);
    }

    public function test_get_top_debtors(): void
    {
        Customer::factory()->create(['total_debt' => 500000, 'name' => 'High Debtor']);
        Customer::factory()->create(['total_debt' => 100000, 'name' => 'Low Debtor']);
        Customer::factory()->create(['total_debt' => 0, 'name' => 'No Debt']);

        $debtors = $this->service->getTopDebtors(10);

        $this->assertCount(2, $debtors);
        $this->assertEquals('High Debtor', $debtors[0]['name']);
        $this->assertEquals(500000, $debtors[0]['total_debt']);
    }

    public function test_get_dashboard_stats_calculates_revenue(): void
    {
        Payment::factory()->create(['amount' => 200000, 'created_at' => now()]);
        Payment::factory()->create(['amount' => 300000, 'created_at' => now()]);

        $stats = $this->service->getDashboardStats('this_month');

        $this->assertEquals(500000, $stats['revenue']['total']);
        $this->assertEquals(2, $stats['revenue']['count']);
    }
}
