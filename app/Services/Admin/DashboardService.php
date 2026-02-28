<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\BillingLog;
use App\Models\Router;
use App\Models\Package;
use App\Models\Area;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // ================================================================
    // STATISTIK UTAMA DASHBOARD
    // ================================================================

    /**
     * Ambil semua statistik untuk dashboard admin
     */
    public function getDashboardStats(?string $period = 'this_month'): array
    {
        $dateRange = $this->getDateRange($period);

        return [
            'overview' => $this->getOverviewStats(),
            'revenue' => $this->getRevenueStats($dateRange),
            'customers' => $this->getCustomerStats(),
            'invoices' => $this->getInvoiceStats($dateRange),
            'collectors' => $this->getCollectorStats($dateRange),
            'charts' => $this->getChartData($period),
            'recent_activities' => $this->getRecentActivities(),
            'alerts' => $this->getAlerts(),
        ];
    }

    // ================================================================
    // OVERVIEW STATS (Card Utama)
    // ================================================================

    /**
     * Statistik overview utama
     */
    protected function getOverviewStats(): array
    {
        return Cache::remember('dashboard:overview', 300, function () {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            // Total Pelanggan
            $totalCustomers = Customer::whereNull('deleted_at')->count();
            $activeCustomers = Customer::where('status', 'active')->count();
            $isolatedCustomers = Customer::where('status', 'isolated')->count();

            // Pelanggan baru bulan ini
            $newCustomersThisMonth = Customer::where('created_at', '>=', $thisMonth)->count();
            $newCustomersLastMonth = Customer::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();
            $customerGrowth = $newCustomersLastMonth > 0
                ? round((($newCustomersThisMonth - $newCustomersLastMonth) / $newCustomersLastMonth) * 100, 1)
                : 100;

            // Total Pendapatan Bulan Ini
            $revenueThisMonth = Payment::where('created_at', '>=', $thisMonth)->sum('amount');
            $revenueLastMonth = Payment::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('amount');
            $revenueGrowth = $revenueLastMonth > 0
                ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
                : 100;

            // Total Hutang
            $totalDebt = Customer::sum('total_debt');

            // Pendapatan Hari Ini
            $revenueToday = Payment::whereDate('created_at', $today)->sum('amount');

            return [
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'isolated_customers' => $isolatedCustomers,
                'new_customers_this_month' => $newCustomersThisMonth,
                'customer_growth' => $customerGrowth,
                'revenue_this_month' => $revenueThisMonth,
                'revenue_growth' => $revenueGrowth,
                'revenue_today' => $revenueToday,
                'total_debt' => $totalDebt,
                'collection_rate' => $this->calculateCollectionRate(),
            ];
        });
    }

    /**
     * Hitung tingkat penagihan
     */
    protected function calculateCollectionRate(): float
    {
        $thisMonth = Carbon::now()->startOfMonth();

        $totalBilled = Invoice::where('created_at', '>=', $thisMonth)
            ->sum('total_amount');

        $totalPaid = Invoice::where('created_at', '>=', $thisMonth)
            ->sum('paid_amount');

        if ($totalBilled <= 0) return 0;

        return round(($totalPaid / $totalBilled) * 100, 1);
    }

    // ================================================================
    // REVENUE STATS
    // ================================================================

    /**
     * Statistik pendapatan
     */
    protected function getRevenueStats(array $dateRange): array
    {
        $cacheKey = 'dashboard:revenue:' . $dateRange['start']->format('Y-m-d') . '_' . $dateRange['end']->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($dateRange) {
            $payments = Payment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->get();

            $byMethod = $payments->groupBy('payment_method')->map(function ($items, $method) {
                return [
                    'method' => $method,
                    'count' => $items->count(),
                    'total' => $items->sum('amount'),
                ];
            })->values();

            return [
                'total' => $payments->sum('amount'),
                'count' => $payments->count(),
                'average' => $payments->count() > 0 ? $payments->avg('amount') : 0,
                'by_method' => $byMethod,
                'cash' => $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'other' => $payments->whereNotIn('payment_method', ['cash', 'transfer'])->sum('amount'),
            ];
        });
    }

    // ================================================================
    // CUSTOMER STATS
    // ================================================================

    /**
     * Statistik pelanggan
     */
    protected function getCustomerStats(): array
    {
        return Cache::remember('dashboard:customers', 600, function () {
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // Status pelanggan
            $byStatus = Customer::select('status', DB::raw('count(*) as total'))
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            // Per paket
            $byPackage = Customer::select('package_id', DB::raw('count(*) as total'))
                ->whereNull('deleted_at')
                ->groupBy('package_id')
                ->with('package:id,name')
                ->get()
                ->map(fn($item) => [
                    'package' => $item->package?->name ?? 'Unknown',
                    'total' => $item->total,
                ]);

            // Per area
            $byArea = Customer::select('area_id', DB::raw('count(*) as total'))
                ->whereNull('deleted_at')
                ->whereNotNull('area_id')
                ->groupBy('area_id')
                ->with('area:id,name')
                ->get()
                ->map(fn($item) => [
                    'area' => $item->area?->name ?? 'Unknown',
                    'total' => $item->total,
                ]);

            // Pelanggan dengan hutang
            $withDebt = Customer::where('total_debt', '>', 0)->count();

            // Pembayaran bulan ini
            $paidThisMonth = Invoice::where('period_year', $currentYear)
                ->where('period_month', $currentMonth)
                ->where('status', 'paid')
                ->count();

            $unpaidThisMonth = Invoice::where('period_year', $currentYear)
                ->where('period_month', $currentMonth)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->count();

            return [
                'by_status' => $byStatus,
                'by_package' => $byPackage,
                'by_area' => $byArea,
                'with_debt' => $withDebt,
                'paid_this_month' => $paidThisMonth,
                'unpaid_this_month' => $unpaidThisMonth,
            ];
        });
    }

    // ================================================================
    // INVOICE STATS
    // ================================================================

    /**
     * Statistik invoice
     */
    protected function getInvoiceStats(array $dateRange): array
    {
        $cacheKey = 'dashboard:invoices:' . $dateRange['start']->format('Y-m-d') . '_' . $dateRange['end']->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($dateRange) {
            $invoices = Invoice::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->get();

            $byStatus = $invoices->groupBy('status')->map(function ($items, $status) {
                return [
                    'status' => $status,
                    'count' => $items->count(),
                    'total' => $items->sum('total_amount'),
                    'paid' => $items->sum('paid_amount'),
                    'remaining' => $items->sum('remaining_amount'),
                ];
            })->values();

            // Invoice overdue
            $overdue = Invoice::where('status', 'overdue')
                ->where('due_date', '<', now())
                ->get();

            return [
                'total' => $invoices->count(),
                'total_amount' => $invoices->sum('total_amount'),
                'total_paid' => $invoices->sum('paid_amount'),
                'total_remaining' => $invoices->sum('remaining_amount'),
                'by_status' => $byStatus,
                'overdue' => [
                    'count' => $overdue->count(),
                    'total' => $overdue->sum('remaining_amount'),
                ],
            ];
        });
    }

    // ================================================================
    // COLLECTOR (PENAGIH) STATS
    // ================================================================

    /**
     * Statistik performa penagih — optimized with GROUP BY
     */
    protected function getCollectorStats(array $dateRange): array
    {
        $cacheKey = 'dashboard:collectors:' . $dateRange['start']->format('Y-m-d') . '_' . $dateRange['end']->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($dateRange) {
            $collectors = User::where('role', 'penagih')
                ->where('is_active', true)
                ->get();

            if ($collectors->isEmpty()) {
                return [
                    'collectors' => [],
                    'total_collectors' => 0,
                    'total_collected' => 0,
                    'pending_expenses' => Expense::where('status', 'pending')->count(),
                    'pending_settlements' => Settlement::where('status', 'pending')->count(),
                ];
            }

            $collectorIds = $collectors->pluck('id');

            // Batch: assigned customers per collector
            $assignedCounts = Customer::select('collector_id', DB::raw('count(*) as total'))
                ->whereIn('collector_id', $collectorIds)
                ->groupBy('collector_id')
                ->pluck('total', 'collector_id');

            // Batch: payments per collector
            $paymentStats = Payment::select(
                    'collector_id',
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('COUNT(*) as total_count')
                )
                ->whereIn('collector_id', $collectorIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->groupBy('collector_id')
                ->get()
                ->keyBy('collector_id');

            // Batch: expenses per collector
            $expenseStats = Expense::select('user_id', DB::raw('SUM(amount) as total_amount'))
                ->whereIn('user_id', $collectorIds)
                ->whereBetween('expense_date', [
                    $dateRange['start']->toDateString(),
                    $dateRange['end']->toDateString()
                ])
                ->where('status', 'approved')
                ->groupBy('user_id')
                ->pluck('total_amount', 'user_id');

            $stats = [];

            foreach ($collectors as $collector) {
                $assigned = $assignedCounts[$collector->id] ?? 0;
                $payment = $paymentStats[$collector->id] ?? null;
                $collected = (float) ($payment?->total_amount ?? 0);
                $transactions = (int) ($payment?->total_count ?? 0);
                $expenses = (float) ($expenseStats[$collector->id] ?? 0);

                $stats[] = [
                    'id' => $collector->id,
                    'name' => $collector->name,
                    'phone' => $collector->phone,
                    'assigned_customers' => $assigned,
                    'collected' => $collected,
                    'transactions' => $transactions,
                    'expenses' => $expenses,
                    'net_collection' => $collected - $expenses,
                ];
            }

            // Sort by collected amount (descending)
            usort($stats, fn($a, $b) => $b['collected'] <=> $a['collected']);

            // Pending expenses untuk verifikasi
            $pendingExpenses = Expense::where('status', 'pending')->count();

            // Pending settlements
            $pendingSettlements = Settlement::where('status', 'pending')->count();

            return [
                'collectors' => $stats,
                'total_collectors' => count($stats),
                'total_collected' => array_sum(array_column($stats, 'collected')),
                'pending_expenses' => $pendingExpenses,
                'pending_settlements' => $pendingSettlements,
            ];
        });
    }

    // ================================================================
    // CHART DATA
    // ================================================================

    /**
     * Data untuk grafik
     */
    protected function getChartData(string $period): array
    {
        return [
            'revenue_trend' => $this->getRevenueTrend(),
            'customer_trend' => $this->getCustomerTrend(),
            'payment_methods' => $this->getPaymentMethodDistribution(),
            'package_distribution' => $this->getPackageDistribution(),
        ];
    }

    /**
     * Trend pendapatan 12 bulan terakhir — optimized: 1 query with GROUP BY
     */
    protected function getRevenueTrend(): array
    {
        return Cache::remember('dashboard:chart:revenue_trend', 900, function () {
            $start = Carbon::now()->subMonths(11)->startOfMonth();

            [$yearExpr, $monthExpr] = $this->getYearMonthExpressions('created_at');

            $revenues = Payment::select(
                    DB::raw("{$yearExpr} as year"),
                    DB::raw("{$monthExpr} as month"),
                    DB::raw('SUM(amount) as total')
                )
                ->where('created_at', '>=', $start)
                ->groupBy(DB::raw($yearExpr), DB::raw($monthExpr))
                ->get()
                ->keyBy(fn($item) => $item->year . '-' . $item->month);

            $data = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $key = $month->year . '-' . $month->month;

                $data[] = [
                    'month' => $month->format('M Y'),
                    'revenue' => (float) ($revenues[$key]?->total ?? 0),
                ];
            }

            return $data;
        });
    }

    /**
     * Trend pertumbuhan pelanggan 12 bulan terakhir — optimized: 2 queries with GROUP BY
     */
    protected function getCustomerTrend(): array
    {
        return Cache::remember('dashboard:chart:customer_trend', 900, function () {
            $start = Carbon::now()->subMonths(11)->startOfMonth();

            [$yearExprCreated, $monthExprCreated] = $this->getYearMonthExpressions('created_at');
            [$yearExprDeleted, $monthExprDeleted] = $this->getYearMonthExpressions('deleted_at');

            $newCustomers = Customer::select(
                    DB::raw("{$yearExprCreated} as year"),
                    DB::raw("{$monthExprCreated} as month"),
                    DB::raw('COUNT(*) as total')
                )
                ->where('created_at', '>=', $start)
                ->groupBy(DB::raw($yearExprCreated), DB::raw($monthExprCreated))
                ->get()
                ->keyBy(fn($item) => $item->year . '-' . $item->month);

            $churnCustomers = Customer::select(
                    DB::raw("{$yearExprDeleted} as year"),
                    DB::raw("{$monthExprDeleted} as month"),
                    DB::raw('COUNT(*) as total')
                )
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '>=', $start)
                ->groupBy(DB::raw($yearExprDeleted), DB::raw($monthExprDeleted))
                ->get()
                ->keyBy(fn($item) => $item->year . '-' . $item->month);

            $data = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $key = $month->year . '-' . $month->month;

                $data[] = [
                    'month' => $month->format('M Y'),
                    'new' => (int) ($newCustomers[$key]?->total ?? 0),
                    'churn' => (int) ($churnCustomers[$key]?->total ?? 0),
                ];
            }

            return $data;
        });
    }

    /**
     * Distribusi metode pembayaran
     */
    protected function getPaymentMethodDistribution(): array
    {
        return Cache::remember('dashboard:chart:payment_methods', 300, function () {
            $thisMonth = Carbon::now()->startOfMonth();

            return Payment::where('created_at', '>=', $thisMonth)
                ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->map(fn($item) => [
                    'method' => ucfirst($item->payment_method),
                    'count' => $item->count,
                    'total' => $item->total,
                ])
                ->toArray();
        });
    }

    /**
     * Distribusi paket
     */
    protected function getPackageDistribution(): array
    {
        return Cache::remember('dashboard:chart:packages', 600, function () {
            return Package::withCount(['customers' => function ($query) {
                $query->whereNull('deleted_at');
            }])
                ->where('is_active', true)
                ->get()
                ->map(fn($pkg) => [
                    'name' => $pkg->name,
                    'count' => $pkg->customers_count,
                    'price' => $pkg->price,
                ])
                ->toArray();
        });
    }

    // ================================================================
    // RECENT ACTIVITIES
    // ================================================================

    /**
     * Aktivitas terbaru
     */
    protected function getRecentActivities(int $limit = 20): array
    {
        return Cache::remember('dashboard:recent_activities', 60, function () use ($limit) {
            return BillingLog::with(['loggable', 'performedBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    // Check if loggable is a Customer
                    $isCustomer = $log->loggable_type === 'App\\Models\\Customer';
                    $customerName = null;
                    $customerId = null;

                    if ($isCustomer && $log->loggable) {
                        $customerName = $log->loggable->name;
                        $customerId = $log->loggable->customer_id;
                    }

                    return [
                        'id' => $log->id,
                        'type' => $log->action,
                        'action' => $log->action,
                        'action_label' => $log->action_label,
                        'description' => $log->description,
                        'customer' => $customerName,
                        'customer_id' => $customerId,
                        'performed_by' => $log->performedBy?->name,
                        'created_at' => $log->created_at->diffForHumans(),
                    ];
                })
                ->toArray();
        });
    }

    // ================================================================
    // ALERTS & NOTIFICATIONS
    // ================================================================

    /**
     * Alert dan notifikasi penting
     */
    protected function getAlerts(): array
    {
        return Cache::remember('dashboard:alerts', 180, function () {
            $alerts = [];

            // Pelanggan akan diisolir (hutang 2 bulan)
            $willBeIsolated = Customer::where('status', 'active')
                ->where('total_debt', '>=', function ($query) {
                    $query->selectRaw('price * 2')
                        ->from('packages')
                        ->whereColumn('packages.id', 'customers.package_id');
                })
                ->count();

            if ($willBeIsolated > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Pelanggan Akan Diisolir',
                    'message' => "{$willBeIsolated} pelanggan memiliki hutang 2 bulan atau lebih",
                    'action_url' => '/admin/customers?will_isolate=1',
                ];
            }

            // Invoice overdue
            $overdueInvoices = Invoice::where('status', 'overdue')
                ->where('due_date', '<', now()->subDays(7))
                ->count();

            if ($overdueInvoices > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Invoice Overdue',
                    'message' => "{$overdueInvoices} invoice sudah lewat jatuh tempo lebih dari 7 hari",
                    'action_url' => '/admin/invoices?status=overdue',
                ];
            }

            // Pengeluaran pending verifikasi
            $pendingExpenses = Expense::where('status', 'pending')->count();

            if ($pendingExpenses > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Pengeluaran Perlu Verifikasi',
                    'message' => "{$pendingExpenses} pengeluaran penagih menunggu verifikasi",
                    'action_url' => '/admin/expenses?status=pending',
                ];
            }

            // Settlement pending
            $pendingSettlements = Settlement::where('status', 'pending')->count();

            if ($pendingSettlements > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Setoran Perlu Verifikasi',
                    'message' => "{$pendingSettlements} setoran penagih menunggu verifikasi",
                    'action_url' => '/admin/settlements?status=pending',
                ];
            }

            // Router offline (jika ada)
            $offlineRouters = Router::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('last_sync_at')
                        ->orWhere('last_sync_at', '<', now()->subHours(1));
                })
                ->count();

            if ($offlineRouters > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Router Tidak Merespons',
                    'message' => "{$offlineRouters} router tidak merespons lebih dari 1 jam",
                    'action_url' => '/admin/routers?status=offline',
                ];
            }

            return $alerts;
        });
    }

    // ================================================================
    // TOP PERFORMERS
    // ================================================================

    /**
     * Top pelanggan berdasarkan pembayaran
     */
    public function getTopPayingCustomers(int $limit = 10): array
    {
        return Cache::remember('dashboard:top_paying', 600, function () use ($limit) {
            $thisYear = Carbon::now()->year;

            return Customer::select('customers.*')
                ->selectRaw('(SELECT SUM(amount) FROM payments WHERE payments.customer_id = customers.id AND YEAR(payments.created_at) = ?) as total_paid', [$thisYear])
                ->whereNull('deleted_at')
                ->orderByDesc('total_paid')
                ->limit($limit)
                ->get()
                ->map(fn($c) => [
                    'customer_id' => $c->customer_id,
                    'name' => $c->name,
                    'total_paid' => $c->total_paid ?? 0,
                ])
                ->toArray();
        });
    }

    /**
     * Pelanggan dengan hutang terbanyak
     */
    public function getTopDebtors(int $limit = 10): array
    {
        return Cache::remember('dashboard:top_debtors', 300, function () use ($limit) {
            return Customer::whereNull('deleted_at')
                ->where('total_debt', '>', 0)
                ->orderByDesc('total_debt')
                ->limit($limit)
                ->get(['customer_id', 'name', 'total_debt', 'status'])
                ->toArray();
        });
    }

    // ================================================================
    // CACHE INVALIDATION
    // ================================================================

    /**
     * Clear all dashboard cache keys
     */
    public static function clearDashboardCache(): void
    {
        $keys = [
            'dashboard:overview',
            'dashboard:customers',
            'dashboard:chart:revenue_trend',
            'dashboard:chart:customer_trend',
            'dashboard:chart:payment_methods',
            'dashboard:chart:packages',
            'dashboard:recent_activities',
            'dashboard:alerts',
            'dashboard:top_paying',
            'dashboard:top_debtors',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    // ================================================================
    // HELPER
    // ================================================================

    /**
     * Get DB-driver-compatible YEAR/MONTH expressions.
     */
    protected function getYearMonthExpressions(string $column): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return [
                "CAST(strftime('%Y', {$column}) AS INTEGER)",
                "CAST(strftime('%m', {$column}) AS INTEGER)",
            ];
        }

        return ["YEAR({$column})", "MONTH({$column})"];
    }

    protected function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [
                'start' => Carbon::today()->startOfDay(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                'start' => Carbon::now()->startOfWeek(),
                'end' => Carbon::now()->endOfWeek(),
            ],
            'this_month' => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
            'last_month' => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                'start' => Carbon::now()->startOfYear(),
                'end' => Carbon::now()->endOfYear(),
            ],
            default => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth(),
            ],
        };
    }
}
