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
    }

    // ================================================================
    // CUSTOMER STATS
    // ================================================================

    /**
     * Statistik pelanggan
     */
    protected function getCustomerStats(): array
    {
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
    }

    // ================================================================
    // INVOICE STATS
    // ================================================================

    /**
     * Statistik invoice
     */
    protected function getInvoiceStats(array $dateRange): array
    {
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
    }

    // ================================================================
    // COLLECTOR (PENAGIH) STATS
    // ================================================================

    /**
     * Statistik performa penagih
     */
    protected function getCollectorStats(array $dateRange): array
    {
        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->get();

        $stats = [];

        foreach ($collectors as $collector) {
            // Jumlah pelanggan yang ditugaskan
            $assignedCustomers = Customer::where('collector_id', $collector->id)->count();

            // Pembayaran yang dikumpulkan
            $collected = Payment::where('collector_id', $collector->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            // Jumlah transaksi
            $transactions = Payment::where('collector_id', $collector->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            // Pengeluaran
            $expenses = Expense::where('user_id', $collector->id)
                ->whereBetween('expense_date', [
                    $dateRange['start']->toDateString(),
                    $dateRange['end']->toDateString()
                ])
                ->where('status', 'approved')
                ->sum('amount');

            $stats[] = [
                'id' => $collector->id,
                'name' => $collector->name,
                'phone' => $collector->phone,
                'assigned_customers' => $assignedCustomers,
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
     * Trend pendapatan 12 bulan terakhir
     */
    protected function getRevenueTrend(): array
    {
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);

            $revenue = Payment::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('amount');

            $data[] = [
                'month' => $month->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return $data;
    }

    /**
     * Trend pertumbuhan pelanggan 12 bulan terakhir
     */
    protected function getCustomerTrend(): array
    {
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);

            $newCustomers = Customer::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $churn = Customer::whereYear('deleted_at', $month->year)
                ->whereMonth('deleted_at', $month->month)
                ->count();

            $data[] = [
                'month' => $month->format('M Y'),
                'new' => $newCustomers,
                'churn' => $churn,
            ];
        }

        return $data;
    }

    /**
     * Distribusi metode pembayaran
     */
    protected function getPaymentMethodDistribution(): array
    {
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
    }

    /**
     * Distribusi paket
     */
    protected function getPackageDistribution(): array
    {
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
    }

    // ================================================================
    // RECENT ACTIVITIES
    // ================================================================

    /**
     * Aktivitas terbaru
     */
    protected function getRecentActivities(int $limit = 20): array
    {
        return BillingLog::with(['customer:id,customer_id,name', 'performedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'id' => $log->id,
                'type' => $log->log_type,
                'status' => $log->status,
                'title' => $log->title,
                'description' => $log->description,
                'customer' => $log->customer?->name,
                'customer_id' => $log->customer?->customer_id,
                'performed_by' => $log->performedBy?->name,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    // ================================================================
    // ALERTS & NOTIFICATIONS
    // ================================================================

    /**
     * Alert dan notifikasi penting
     */
    protected function getAlerts(): array
    {
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
                'action_url' => '/customers?filter=will_isolate',
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
                'action_url' => '/invoices?filter=overdue',
            ];
        }

        // Pengeluaran pending verifikasi
        $pendingExpenses = Expense::where('status', 'pending')->count();

        if ($pendingExpenses > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Pengeluaran Perlu Verifikasi',
                'message' => "{$pendingExpenses} pengeluaran penagih menunggu verifikasi",
                'action_url' => '/admin/expenses/pending',
            ];
        }

        // Settlement pending
        $pendingSettlements = Settlement::where('status', 'pending')->count();

        if ($pendingSettlements > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Setoran Perlu Verifikasi',
                'message' => "{$pendingSettlements} setoran penagih menunggu verifikasi",
                'action_url' => '/admin/settlements/pending',
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
                'action_url' => '/routers?filter=offline',
            ];
        }

        return $alerts;
    }

    // ================================================================
    // TOP PERFORMERS
    // ================================================================

    /**
     * Top pelanggan berdasarkan pembayaran
     */
    public function getTopPayingCustomers(int $limit = 10): array
    {
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
    }

    /**
     * Pelanggan dengan hutang terbanyak
     */
    public function getTopDebtors(int $limit = 10): array
    {
        return Customer::whereNull('deleted_at')
            ->where('total_debt', '>', 0)
            ->orderByDesc('total_debt')
            ->limit($limit)
            ->get(['customer_id', 'name', 'total_debt', 'status'])
            ->toArray();
    }

    // ================================================================
    // HELPER
    // ================================================================

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
