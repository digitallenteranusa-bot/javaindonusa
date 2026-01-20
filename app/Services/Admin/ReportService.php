<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Area;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get revenue overview
     */
    public function getRevenueOverview(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;

        $query = Invoice::whereNotIn('status', ['cancelled']);

        if ($month) {
            $query->where('period_year', $year)->where('period_month', $month);
        } else {
            $query->where('period_year', $year);
        }

        $invoices = $query->get();

        $totalBilled = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('paid_amount');
        $totalOutstanding = $invoices->sum('remaining_amount');

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => $totalBilled > 0
                ? round(($totalPaid / $totalBilled) * 100, 1)
                : 0,
            'invoice_count' => $invoices->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
            'pending_count' => $invoices->whereIn('status', ['pending', 'partial'])->count(),
            'overdue_count' => $invoices->where('status', 'overdue')->count(),
        ];
    }

    /**
     * Get monthly revenue trend
     */
    public function getMonthlyRevenueTrend(int $year): array
    {
        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            $invoices = Invoice::where('period_year', $year)
                ->where('period_month', $m)
                ->whereNotIn('status', ['cancelled'])
                ->get();

            $totalBilled = $invoices->sum('total_amount');
            $totalPaid = $invoices->sum('paid_amount');

            $months[] = [
                'month' => $m,
                'month_name' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'billed' => $totalBilled,
                'paid' => $totalPaid,
                'collection_rate' => $totalBilled > 0
                    ? round(($totalPaid / $totalBilled) * 100, 1)
                    : 0,
            ];
        }

        return $months;
    }

    /**
     * Get payment summary by method
     */
    public function getPaymentByMethod(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::where('status', 'success');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $payments = $query->get();

        return [
            'cash' => [
                'amount' => $payments->where('payment_method', 'cash')->sum('amount'),
                'count' => $payments->where('payment_method', 'cash')->count(),
            ],
            'transfer' => [
                'amount' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'count' => $payments->where('payment_method', 'transfer')->count(),
            ],
            'total' => [
                'amount' => $payments->sum('amount'),
                'count' => $payments->count(),
            ],
        ];
    }

    /**
     * Get collector performance
     */
    public function getCollectorPerformance(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->withCount(['assignedCustomers' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        $performance = [];

        foreach ($collectors as $collector) {
            // Get payments collected by this collector
            $payments = Payment::where('collector_id', $collector->id)
                ->where('status', 'success')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            // Get total billable for assigned customers
            $totalBillable = Invoice::whereHas('customer', function ($q) use ($collector) {
                    $q->where('collector_id', $collector->id);
                })
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount');

            $totalCollected = $payments->sum('amount');

            $performance[] = [
                'id' => $collector->id,
                'name' => $collector->name,
                'customers_count' => $collector->assigned_customers_count,
                'total_billable' => $totalBillable,
                'total_collected' => $totalCollected,
                'collection_rate' => $totalBillable > 0
                    ? round(($totalCollected / $totalBillable) * 100, 1)
                    : 0,
                'transactions' => $payments->count(),
                'cash_collected' => $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer_collected' => $payments->where('payment_method', 'transfer')->sum('amount'),
            ];
        }

        // Sort by collection rate descending
        usort($performance, fn($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return $performance;
    }

    /**
     * Get area performance
     */
    public function getAreaPerformance(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $areas = Area::where('is_active', true)
            ->withCount(['customers' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        $performance = [];

        foreach ($areas as $area) {
            $invoices = Invoice::whereHas('customer', function ($q) use ($area) {
                    $q->where('area_id', $area->id);
                })
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->whereNotIn('status', ['cancelled'])
                ->get();

            $totalBilled = $invoices->sum('total_amount');
            $totalPaid = $invoices->sum('paid_amount');

            $performance[] = [
                'id' => $area->id,
                'name' => $area->name,
                'customers_count' => $area->customers_count,
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'outstanding' => $totalBilled - $totalPaid,
                'collection_rate' => $totalBilled > 0
                    ? round(($totalPaid / $totalBilled) * 100, 1)
                    : 0,
                'paid_count' => $invoices->where('status', 'paid')->count(),
                'overdue_count' => $invoices->where('status', 'overdue')->count(),
            ];
        }

        // Sort by collection rate descending
        usort($performance, fn($a, $b) => $b['collection_rate'] <=> $a['collection_rate']);

        return $performance;
    }

    /**
     * Get customer status summary
     */
    public function getCustomerStatusSummary(): array
    {
        $customers = Customer::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($customers);

        return [
            'total' => $total,
            'active' => $customers['active'] ?? 0,
            'isolated' => $customers['isolated'] ?? 0,
            'suspended' => $customers['suspended'] ?? 0,
            'inactive' => $customers['inactive'] ?? 0,
            'active_percentage' => $total > 0
                ? round((($customers['active'] ?? 0) / $total) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get debt aging report
     */
    public function getDebtAging(): array
    {
        $today = now();

        $customers = Customer::where('total_debt', '>', 0)
            ->with(['invoices' => function ($q) {
                $q->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('due_date');
            }])
            ->get();

        $aging = [
            'current' => ['count' => 0, 'amount' => 0],      // Not yet due
            '1_30' => ['count' => 0, 'amount' => 0],         // 1-30 days overdue
            '31_60' => ['count' => 0, 'amount' => 0],        // 31-60 days
            '61_90' => ['count' => 0, 'amount' => 0],        // 61-90 days
            'over_90' => ['count' => 0, 'amount' => 0],      // Over 90 days
        ];

        foreach ($customers as $customer) {
            $oldestInvoice = $customer->invoices->first();

            if (!$oldestInvoice) continue;

            $daysOverdue = $today->diffInDays($oldestInvoice->due_date, false);

            if ($daysOverdue <= 0) {
                $aging['current']['count']++;
                $aging['current']['amount'] += $customer->total_debt;
            } elseif ($daysOverdue <= 30) {
                $aging['1_30']['count']++;
                $aging['1_30']['amount'] += $customer->total_debt;
            } elseif ($daysOverdue <= 60) {
                $aging['31_60']['count']++;
                $aging['31_60']['amount'] += $customer->total_debt;
            } elseif ($daysOverdue <= 90) {
                $aging['61_90']['count']++;
                $aging['61_90']['amount'] += $customer->total_debt;
            } else {
                $aging['over_90']['count']++;
                $aging['over_90']['amount'] += $customer->total_debt;
            }
        }

        return $aging;
    }

    /**
     * Get daily payment trend for current month
     */
    public function getDailyPaymentTrend(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $daysInMonth = $endDate->day;

        $payments = Payment::where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DAY(created_at) as day'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DAY(created_at)'))
            ->pluck('total', 'day')
            ->toArray();

        $trend = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $trend[] = [
                'day' => $d,
                'amount' => $payments[$d] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * Get top debtors
     */
    public function getTopDebtors(int $limit = 10): array
    {
        return Customer::where('total_debt', '>', 0)
            ->with(['area:id,name', 'package:id,name'])
            ->orderByDesc('total_debt')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'customer_id' => $c->customer_id,
                'name' => $c->name,
                'area' => $c->area?->name,
                'package' => $c->package?->name,
                'total_debt' => $c->total_debt,
                'status' => $c->status,
            ])
            ->toArray();
    }

    /**
     * Get available years for filtering
     */
    public function getAvailableYears(): array
    {
        return Invoice::selectRaw('DISTINCT period_year')
            ->orderByDesc('period_year')
            ->pluck('period_year')
            ->toArray();
    }
}
