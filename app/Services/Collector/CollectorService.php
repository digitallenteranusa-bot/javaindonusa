<?php

namespace App\Services\Collector;

use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\CollectionLog;
use App\Services\Billing\DebtIsolationService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CollectorService
{
    protected DebtIsolationService $debtService;
    protected NotificationService $notificationService;

    public function __construct(DebtIsolationService $debtService, NotificationService $notificationService)
    {
        $this->debtService = $debtService;
        $this->notificationService = $notificationService;
    }

    // ================================================================
    // STATISTIK DASHBOARD PENAGIH
    // ================================================================

    /**
     * Ambil statistik ringkas untuk dashboard penagih
     */
    public function getDashboardStats(User $collector, ?string $period = 'today'): array
    {
        $dateRange = $this->getDateRange($period);

        // Ambil ID pelanggan yang ditugaskan ke penagih ini
        $customerIds = $this->getAssignedCustomerIds($collector);

        return [
            'customers' => $this->getCustomerStats($customerIds),
            'revenue' => $this->getRevenueStats($collector, $customerIds, $dateRange),
            'collection' => $this->getCollectionStats($collector, $dateRange),
            'settlement' => $this->getSettlementInfo($collector, $dateRange),
        ];
    }

    /**
     * Statistik pelanggan (total, sudah bayar, belum bayar)
     */
    protected function getCustomerStats(array $customerIds): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $total = count($customerIds);

        // Pelanggan yang sudah bayar bulan ini
        $paidThisMonth = Invoice::whereIn('customer_id', $customerIds)
            ->where('period_month', $currentMonth)
            ->where('period_year', $currentYear)
            ->where('status', 'paid')
            ->count();

        // Pelanggan dengan status terisolir
        $isolated = Customer::whereIn('id', $customerIds)
            ->where('status', 'isolated')
            ->count();

        return [
            'total' => $total,
            'paid_this_month' => $paidThisMonth,
            'unpaid_this_month' => $total - $paidThisMonth,
            'isolated' => $isolated,
            'active' => $total - $isolated,
        ];
    }

    /**
     * Statistik pendapatan tagihan
     */
    protected function getRevenueStats(User $collector, array $customerIds, array $dateRange): array
    {
        // Total tagihan yang harus ditagih
        $totalBillable = Invoice::whereIn('customer_id', $customerIds)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('remaining_amount');

        // Total yang sudah dibayar (by this collector dalam periode)
        $collected = Payment::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        // Total hutang pelanggan
        $totalDebt = Customer::whereIn('id', $customerIds)
            ->sum('total_debt');

        return [
            'total_billable' => $totalBillable,
            'collected' => $collected,
            'total_debt' => $totalDebt,
            'collection_rate' => $totalBillable > 0
                ? round(($collected / $totalBillable) * 100, 2)
                : 0,
        ];
    }

    /**
     * Statistik aktivitas penagihan
     */
    protected function getCollectionStats(User $collector, array $dateRange): array
    {
        $logs = CollectionLog::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        return [
            'total_visits' => $logs->where('action_type', 'visit')->count(),
            'payments_cash' => $logs->where('action_type', 'payment_cash')->count(),
            'payments_transfer' => $logs->where('action_type', 'payment_transfer')->count(),
            'not_home' => $logs->where('action_type', 'not_home')->count(),
            'refused' => $logs->where('action_type', 'refused')->count(),
            'reminders_sent' => $logs->where('action_type', 'reminder_sent')->count(),
        ];
    }

    /**
     * Info setoran penagih
     */
    protected function getSettlementInfo(User $collector, array $dateRange): array
    {
        return $this->calculateFinalSettlement($collector, $dateRange['start'], $dateRange['end']);
    }

    // ================================================================
    // DAFTAR PELANGGAN MENUNGGAK
    // ================================================================

    /**
     * Ambil daftar pelanggan menunggak untuk penagih
     */
    public function getOverdueCustomers(
        User $collector,
        ?string $search = null,
        ?string $status = null,
        ?string $paymentStatus = null,
        int $perPage = 20
    ) {
        $customerIds = $this->getAssignedCustomerIds($collector);

        $query = Customer::whereIn('id', $customerIds)
            ->with(['package', 'area', 'invoices' => function ($q) {
                $q->whereIn('status', ['pending', 'partial', 'overdue', 'paid'])
                    ->orderBy('period_year', 'desc')
                    ->orderBy('period_month', 'desc');
            }]);

        // Filter berdasarkan payment status
        $currentMonth = now()->month;
        $currentYear = now()->year;

        if ($paymentStatus === 'paid') {
            // Pelanggan yang sudah lunas = punya invoice bulan ini DAN sudah dibayar
            $query->whereHas('invoices', function ($q) use ($currentMonth, $currentYear) {
                $q->where('period_month', $currentMonth)
                    ->where('period_year', $currentYear)
                    ->where('status', 'paid');
            });
        } elseif ($paymentStatus === 'unpaid') {
            // Pelanggan yang belum bayar = punya invoice bulan ini tapi belum lunas
            $query->whereHas('invoices', function ($q) use ($currentMonth, $currentYear) {
                $q->where('period_month', $currentMonth)
                    ->where('period_year', $currentYear)
                    ->whereIn('status', ['pending', 'partial', 'overdue']);
            });
        } elseif ($paymentStatus === 'overdue') {
            // Pelanggan dengan invoice overdue
            $query->whereHas('invoices', function ($q) {
                $q->where('status', 'overdue');
            });
        }
        // Default: tampilkan semua pelanggan (tidak filter by debt)

        // Filter berdasarkan nama pelanggan
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan status isolir
        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('total_debt', 'desc')
            ->paginate($perPage);
    }

    /**
     * Ambil daftar pelanggan menunggak untuk Dashboard (hanya yang punya hutang)
     */
    public function getOverdueCustomersForDashboard(User $collector, int $limit = 10)
    {
        $customerIds = $this->getAssignedCustomerIds($collector);

        return Customer::whereIn('id', $customerIds)
            ->where('total_debt', '>', 0)  // Hanya yang punya hutang
            ->with(['package', 'area', 'invoices' => function ($q) {
                $q->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('period_year', 'desc')
                    ->orderBy('period_month', 'desc');
            }])
            ->orderBy('total_debt', 'desc')
            ->limit($limit)
            ->get();
    }

    // ================================================================
    // PROSES PEMBAYARAN OLEH PENAGIH
    // ================================================================

    /**
     * Proses pembayaran tunai oleh penagih
     */
    public function processCashPayment(
        User $collector,
        Customer $customer,
        float $amount,
        ?string $notes = null
    ): array {
        // Validasi: penagih hanya bisa menagih pelanggannya
        if (!$this->isCustomerAssigned($collector, $customer)) {
            throw new \Exception('Anda tidak memiliki akses ke pelanggan ini');
        }

        $result = DB::transaction(function () use ($collector, $customer, $amount, $notes) {
            // 1. Proses pembayaran
            $result = $this->debtService->processPayment(
                $customer,
                $amount,
                'cash',
                $collector->id,
                null,
                $notes
            );

            // 2. Catat log penagihan
            CollectionLog::create([
                'collector_id' => $collector->id,
                'customer_id' => $customer->id,
                'payment_id' => $result['payment']->id,
                'action_type' => 'payment_cash',
                'amount' => $amount,
                'payment_method' => 'cash',
                'visit_time' => now(),
                'notes' => $notes,
            ]);

            return $result;
        });

        // 3. Kirim notifikasi WA ke pelanggan (di luar transaction agar tidak rollback jika gagal)
        $this->sendPaymentNotification($customer, $result);

        return $result;
    }

    /**
     * Proses pembayaran transfer oleh penagih
     */
    public function processTransferPayment(
        User $collector,
        Customer $customer,
        float $amount,
        string $transferProofPath,
        ?string $notes = null
    ): array {
        // Validasi: penagih hanya bisa menagih pelanggannya
        if (!$this->isCustomerAssigned($collector, $customer)) {
            throw new \Exception('Anda tidak memiliki akses ke pelanggan ini');
        }

        $result = DB::transaction(function () use ($collector, $customer, $amount, $transferProofPath, $notes) {
            // 1. Proses pembayaran
            $result = $this->debtService->processPayment(
                $customer,
                $amount,
                'transfer',
                $collector->id,
                $transferProofPath,
                $notes
            );

            // 2. Catat log penagihan
            CollectionLog::create([
                'collector_id' => $collector->id,
                'customer_id' => $customer->id,
                'payment_id' => $result['payment']->id,
                'action_type' => 'payment_transfer',
                'amount' => $amount,
                'payment_method' => 'transfer',
                'transfer_proof' => $transferProofPath,
                'visit_time' => now(),
                'notes' => $notes,
            ]);

            return $result;
        });

        // 3. Kirim notifikasi WA ke pelanggan (di luar transaction agar tidak rollback jika gagal)
        $this->sendPaymentNotification($customer, $result);

        return $result;
    }

    /**
     * Catat kunjungan tanpa pembayaran
     */
    public function logVisit(
        User $collector,
        Customer $customer,
        string $actionType,
        ?string $notes = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): CollectionLog {
        if (!$this->isCustomerAssigned($collector, $customer)) {
            throw new \Exception('Anda tidak memiliki akses ke pelanggan ini');
        }

        return CollectionLog::create([
            'collector_id' => $collector->id,
            'customer_id' => $customer->id,
            'action_type' => $actionType,
            'visit_time' => now(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'notes' => $notes,
        ]);
    }

    // ================================================================
    // KALKULASI SETORAN (FINAL SETTLEMENT)
    // ================================================================

    /**
     * Hitung total uang yang harus disetorkan penagih
     * Total Tagihan Masuk - Total Belanja = Saldo Harus Disetor
     */
    public function calculateFinalSettlement(
        User $collector,
        Carbon|string $startDate,
        Carbon|string $endDate
    ): array {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate)->startOfDay();
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate)->endOfDay();

        // Total pembayaran yang diterima (uang masuk)
        $totalCollection = Payment::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        // Total pembayaran tunai saja (yang harus disetor)
        $cashCollection = Payment::where('collector_id', $collector->id)
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Total pengeluaran/belanja
        $totalExpense = Expense::where('user_id', $collector->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        // Pengeluaran yang sudah diverifikasi
        $approvedExpense = Expense::where('user_id', $collector->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'approved')
            ->sum('amount');

        // Pengeluaran pending (belum diverifikasi)
        $pendingExpense = Expense::where('user_id', $collector->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'pending')
            ->sum('amount');

        // Komisi penagih (jika ada)
        $commissionRate = $collector->commission_rate ?? 0;
        $commissionAmount = $totalCollection * ($commissionRate / 100);

        // Saldo yang harus disetor
        $mustSettle = $cashCollection - $approvedExpense - $commissionAmount;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_collection' => $totalCollection,
            'cash_collection' => $cashCollection,
            'transfer_collection' => $totalCollection - $cashCollection,
            'total_expense' => $totalExpense,
            'approved_expense' => $approvedExpense,
            'pending_expense' => $pendingExpense,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'must_settle' => max(0, $mustSettle),
        ];
    }

    /**
     * Ambil ringkasan harian penagih
     */
    public function getDailySummary(User $collector, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $payments = Payment::where('collector_id', $collector->id)
            ->whereDate('created_at', $date)
            ->with('customer:id,customer_id,name')
            ->get();

        $expenses = Expense::where('user_id', $collector->id)
            ->whereDate('expense_date', $date)
            ->get();

        $visits = CollectionLog::where('collector_id', $collector->id)
            ->whereDate('created_at', $date)
            ->with('customer:id,customer_id,name')
            ->get();

        return [
            'date' => $date->toDateString(),
            'payments' => [
                'count' => $payments->count(),
                'total' => $payments->sum('amount'),
                'cash' => $payments->where('payment_method', 'cash')->sum('amount'),
                'transfer' => $payments->where('payment_method', 'transfer')->sum('amount'),
                'details' => $payments->map(fn($p) => [
                    'time' => $p->created_at->format('H:i'),
                    'customer' => $p->customer->name ?? '-',
                    'amount' => $p->amount,
                    'method' => $p->payment_method,
                ]),
            ],
            'expenses' => [
                'count' => $expenses->count(),
                'total' => $expenses->sum('amount'),
                'details' => $expenses->map(fn($e) => [
                    'category' => $e->category,
                    'amount' => $e->amount,
                    'description' => $e->description,
                    'status' => $e->status,
                ]),
            ],
            'visits' => [
                'count' => $visits->count(),
                'by_type' => $visits->groupBy('action_type')->map->count(),
            ],
            'settlement' => $this->calculateFinalSettlement($collector, $date, $date),
        ];
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Ambil ID pelanggan yang ditugaskan ke penagih
     */
    protected function getAssignedCustomerIds(User $collector): array
    {
        return Customer::where('collector_id', $collector->id)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Cek apakah pelanggan ditugaskan ke penagih
     */
    protected function isCustomerAssigned(User $collector, Customer $customer): bool
    {
        return $customer->collector_id === $collector->id;
    }

    /**
     * Kirim notifikasi pembayaran ke pelanggan via WhatsApp
     */
    protected function sendPaymentNotification(Customer $customer, array $paymentResult): void
    {
        try {
            // Refresh customer untuk mendapatkan data terbaru (total_debt)
            $customer->refresh();

            // Kirim konfirmasi pembayaran
            $this->notificationService->sendPaymentConfirmation($customer, $paymentResult['payment']);

            // Jika akses dibuka (dari isolir), kirim notifikasi tambahan
            if ($paymentResult['access_opened'] ?? false) {
                $this->notificationService->sendAccessOpenedNotice($customer);
            }

            Log::info('Payment notification sent', [
                'customer_id' => $customer->id,
                'payment_id' => $paymentResult['payment']->id,
                'access_opened' => $paymentResult['access_opened'] ?? false,
            ]);
        } catch (\Exception $e) {
            // Log error tapi jangan gagalkan proses pembayaran
            Log::error('Failed to send payment notification', [
                'customer_id' => $customer->id,
                'payment_id' => $paymentResult['payment']->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ambil range tanggal berdasarkan periode
     */
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
            default => [
                'start' => Carbon::today()->startOfDay(),
                'end' => Carbon::today()->endOfDay(),
            ],
        };
    }
}
