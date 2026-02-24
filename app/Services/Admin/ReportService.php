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

        // Invoice data for billing totals
        $invoiceQuery = Invoice::whereNotIn('status', ['cancelled']);

        if ($month) {
            $invoiceQuery->where('period_year', $year)->where('period_month', $month);
        } else {
            $invoiceQuery->where('period_year', $year);
        }

        $invoices = $invoiceQuery->get();
        $totalBilled = $invoices->sum('total_amount');

        // Payment data for actual collections (same period)
        $paymentQuery = Payment::where('status', 'verified');

        if ($month) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
            $paymentQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $paymentQuery->whereYear('created_at', $year);
        }

        $totalCollected = $paymentQuery->sum('amount');
        $totalOutstanding = max(0, $totalBilled - $totalCollected);

        return [
            'billable' => (float) $totalBilled,
            'collected' => (float) $totalCollected,
            'outstanding' => (float) $totalOutstanding,
            'collection_rate' => $totalBilled > 0
                ? round(($totalCollected / $totalBilled) * 100, 1)
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
                'billed' => (float) $totalBilled,
                'paid' => (float) $totalPaid,
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
        $query = Payment::where('status', 'verified');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $payments = $query->get();

        $methods = [];
        $grouped = $payments->groupBy('payment_method');

        foreach ($grouped as $method => $items) {
            $methods[] = [
                'method' => $method,
                'total' => (float) $items->sum('amount'),
                'count' => $items->count(),
            ];
        }

        return $methods;
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
                ->where('status', 'verified')
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
                'total_billable' => (float) $totalBillable,
                'total_collected' => (float) $totalCollected,
                'collection_rate' => $totalBillable > 0
                    ? round(($totalCollected / $totalBillable) * 100, 1)
                    : 0,
                'transactions' => $payments->count(),
                'cash_collected' => (float) $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer_collected' => (float) $payments->where('payment_method', 'transfer')->sum('amount'),
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
                'total_billed' => (float) $totalBilled,
                'total_paid' => (float) $totalPaid,
                'outstanding' => (float) ($totalBilled - $totalPaid),
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
            ['label' => 'Belum Jatuh Tempo', 'months' => 0, 'count' => 0, 'total' => 0],
            ['label' => '1-30 Hari', 'months' => 1, 'count' => 0, 'total' => 0],
            ['label' => '31-60 Hari', 'months' => 2, 'count' => 0, 'total' => 0],
            ['label' => '61-90 Hari', 'months' => 3, 'count' => 0, 'total' => 0],
            ['label' => '> 90 Hari', 'months' => 4, 'count' => 0, 'total' => 0],
        ];

        foreach ($customers as $customer) {
            $oldestInvoice = $customer->invoices->first();

            if (!$oldestInvoice) continue;

            $daysOverdue = $today->diffInDays($oldestInvoice->due_date, false);

            if ($daysOverdue <= 0) {
                $aging[0]['count']++;
                $aging[0]['total'] += $customer->total_debt;
            } elseif ($daysOverdue <= 30) {
                $aging[1]['count']++;
                $aging[1]['total'] += $customer->total_debt;
            } elseif ($daysOverdue <= 60) {
                $aging[2]['count']++;
                $aging[2]['total'] += $customer->total_debt;
            } elseif ($daysOverdue <= 90) {
                $aging[3]['count']++;
                $aging[3]['total'] += $customer->total_debt;
            } else {
                $aging[4]['count']++;
                $aging[4]['total'] += $customer->total_debt;
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

        $payments = Payment::where('status', 'verified')
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
            ->withCount(['invoices as unpaid_months' => function ($q) {
                $q->whereIn('status', ['pending', 'partial', 'overdue']);
            }])
            ->orderByDesc('total_debt')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'customer_id' => $c->customer_id,
                'name' => $c->name,
                'area' => $c->area ? ['name' => $c->area->name] : null,
                'package' => $c->package?->name,
                'total_debt' => $c->total_debt,
                'unpaid_months' => $c->unpaid_months,
                'status' => $c->status,
            ])
            ->toArray();
    }

    /**
     * Get yearly revenue summary with comparison to previous year
     */
    public function getYearlyRevenueSummary(int $year): array
    {
        $currentOverview = $this->getRevenueOverview($year);
        $prevOverview = $this->getRevenueOverview($year - 1);

        $growthPercent = $prevOverview['collected'] > 0
            ? round((($currentOverview['collected'] - $prevOverview['collected']) / $prevOverview['collected']) * 100, 1)
            : 0;

        // Find best month
        $trend = $this->getMonthlyRevenueTrend($year);
        $bestMonth = collect($trend)->sortByDesc('paid')->first();

        return [
            'total_revenue' => $currentOverview['collected'],
            'prev_year_revenue' => $prevOverview['collected'],
            'growth_percent' => $growthPercent,
            'monthly_average' => round($currentOverview['collected'] / 12, 0),
            'best_month' => $bestMonth ? $bestMonth['month_name'] : '-',
            'best_month_amount' => $bestMonth ? $bestMonth['paid'] : 0,
            'collection_rate' => $currentOverview['collection_rate'],
            'total_billed' => $currentOverview['billable'],
            'total_outstanding' => $currentOverview['outstanding'],
        ];
    }

    /**
     * Get year-over-year comparison (month by month)
     */
    public function getYearOverYearComparison(int $year): array
    {
        $currentTrend = $this->getMonthlyRevenueTrend($year);
        $prevTrend = $this->getMonthlyRevenueTrend($year - 1);

        $comparison = [];
        for ($m = 0; $m < 12; $m++) {
            $comparison[] = [
                'month' => $currentTrend[$m]['month'],
                'month_name' => $currentTrend[$m]['month_name'],
                'current_year' => $currentTrend[$m]['paid'],
                'prev_year' => $prevTrend[$m]['paid'],
            ];
        }

        return $comparison;
    }

    /**
     * Get monthly growth rate (MoM)
     */
    public function getMonthlyGrowthRate(int $year): array
    {
        $trend = $this->getMonthlyRevenueTrend($year);
        $rates = [];

        for ($m = 0; $m < 12; $m++) {
            $current = $trend[$m]['paid'];
            $prev = $m > 0 ? $trend[$m - 1]['paid'] : 0;

            $rates[] = [
                'month' => $trend[$m]['month'],
                'month_name' => $trend[$m]['month_name'],
                'revenue' => $current,
                'growth_rate' => $prev > 0
                    ? round((($current - $prev) / $prev) * 100, 1)
                    : 0,
            ];
        }

        return $rates;
    }

    /**
     * Get revenue breakdown by package
     */
    public function getRevenueByPackage(int $year, ?int $month = null): array
    {
        $query = Invoice::whereNotIn('status', ['cancelled'])
            ->where('period_year', $year);

        if ($month) {
            $query->where('period_month', $month);
        }

        return $query->select('package_name', DB::raw('SUM(total_amount) as total_billed'), DB::raw('SUM(paid_amount) as total_paid'), DB::raw('COUNT(*) as count'))
            ->groupBy('package_name')
            ->orderByDesc('total_paid')
            ->get()
            ->map(fn($item) => [
                'name' => $item->package_name ?: 'Tidak Diketahui',
                'total_billed' => (float) $item->total_billed,
                'total_paid' => (float) $item->total_paid,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Get revenue breakdown by area
     */
    public function getRevenueByArea(int $year, ?int $month = null): array
    {
        $query = Invoice::whereNotIn('status', ['cancelled'])
            ->where('period_year', $year)
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->join('areas', 'customers.area_id', '=', 'areas.id');

        if ($month) {
            $query->where('period_month', $month);
        }

        return $query->select('areas.name as area_name', DB::raw('SUM(invoices.total_amount) as total_billed'), DB::raw('SUM(invoices.paid_amount) as total_paid'), DB::raw('COUNT(invoices.id) as count'))
            ->groupBy('areas.id', 'areas.name')
            ->orderByDesc('total_paid')
            ->get()
            ->map(fn($item) => [
                'name' => $item->area_name,
                'total_billed' => (float) $item->total_billed,
                'total_paid' => (float) $item->total_paid,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Get collection rate trend per month
     */
    public function getCollectionRateTrend(int $year): array
    {
        $trend = $this->getMonthlyRevenueTrend($year);

        return collect($trend)->map(fn($m) => [
            'month' => $m['month'],
            'month_name' => $m['month_name'],
            'collection_rate' => $m['collection_rate'],
        ])->toArray();
    }

    /**
     * Get customer metrics: ARPU and active customer count per month
     */
    public function getCustomerMetrics(int $year): array
    {
        $metrics = [];

        for ($m = 1; $m <= 12; $m++) {
            $activeCustomers = Customer::where('status', 'active')
                ->whereDate('created_at', '<=', Carbon::create($year, $m, 1)->endOfMonth())
                ->where(function ($q) use ($year, $m) {
                    $q->whereNull('deleted_at')
                      ->orWhereDate('deleted_at', '>', Carbon::create($year, $m, 1)->endOfMonth());
                })
                ->count();

            $revenue = Invoice::where('period_year', $year)
                ->where('period_month', $m)
                ->whereNotIn('status', ['cancelled'])
                ->sum('paid_amount');

            $arpu = $activeCustomers > 0 ? round($revenue / $activeCustomers, 0) : 0;

            $metrics[] = [
                'month' => $m,
                'month_name' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'active_customers' => $activeCustomers,
                'revenue' => (float) $revenue,
                'arpu' => (float) $arpu,
            ];
        }

        return $metrics;
    }

    /**
     * Get customer growth trend (new vs churn per month)
     */
    public function getCustomerGrowthTrend(int $year): array
    {
        $data = [];

        for ($m = 1; $m <= 12; $m++) {
            $newCustomers = Customer::whereYear('created_at', $year)
                ->whereMonth('created_at', $m)
                ->count();

            $churn = Customer::whereYear('deleted_at', $year)
                ->whereMonth('deleted_at', $m)
                ->count();

            $data[] = [
                'month' => $m,
                'month_name' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'new' => $newCustomers,
                'churn' => $churn,
                'net' => $newCustomers - $churn,
            ];
        }

        return $data;
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
