<?php

namespace App\Services\Collector;

use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\CollectionLog;
use App\Jobs\SendNotificationJob;
use App\Services\Billing\DebtIsolationService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\Collector\UnauthorizedCustomerAccessException;
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

        // Total hutang pelanggan
        $totalDebt = Customer::whereIn('id', $customerIds)
            ->sum('total_debt');

        // Pendapatan bulan ini (cash + transfer)
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $monthPayments = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get();

        $collectedThisMonth = $monthPayments->sum('amount');
        $cashThisMonth = $monthPayments->where('payment_method', 'cash')->sum('amount');
        $transferThisMonth = $monthPayments->where('payment_method', 'transfer')->sum('amount');

        // Pendapatan hari ini (cash + transfer)
        $todayPayments = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->whereDate('created_at', Carbon::today())
            ->get();

        $collectedToday = $todayPayments->sum('amount');
        $cashToday = $todayPayments->where('payment_method', 'cash')->sum('amount');
        $transferToday = $todayPayments->where('payment_method', 'transfer')->sum('amount');

        return [
            'total_billable' => $totalBillable,
            'collected' => $collectedThisMonth,
            'total_debt' => $totalDebt,
            'collection_rate' => $totalBillable > 0
                ? round(($collectedThisMonth / $totalBillable) * 100, 2)
                : 0,
            'today' => [
                'total' => $collectedToday,
                'cash' => $cashToday,
                'transfer' => $transferToday,
                'count' => $todayPayments->count(),
            ],
            'this_month' => [
                'total' => $collectedThisMonth,
                'cash' => $cashThisMonth,
                'transfer' => $transferThisMonth,
                'count' => $monthPayments->count(),
            ],
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
            throw new UnauthorizedCustomerAccessException();
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
            throw new UnauthorizedCustomerAccessException();
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
            throw new UnauthorizedCustomerAccessException();
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
            ->where('status', 'verified')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        // Total pembayaran tunai saja (yang harus disetor)
        $cashCollection = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
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
     * Hitung semua pembayaran yang BELUM disetor
     * Berbeda dengan calculateFinalSettlement yang berdasarkan periode,
     * method ini menghitung dari settlement terakhir yang sudah verified/settled
     */
    public function calculateUnsettledAmount(User $collector): array
    {
        // Cari settlement terakhir yang sudah diverifikasi/selesai
        $lastSettlement = Settlement::where('collector_id', $collector->id)
            ->whereIn('status', [Settlement::STATUS_SETTLED, Settlement::STATUS_VERIFIED])
            ->orderBy('period_end', 'desc')
            ->first();

        // Tanggal mulai = setelah settlement terakhir, atau dari awal jika belum pernah setor
        $startDate = $lastSettlement
            ? Carbon::parse($lastSettlement->period_end)->addDay()->startOfDay()
            : Carbon::create(2020, 1, 1)->startOfDay();

        $endDate = Carbon::now();

        // Total pembayaran cash yang belum disetor
        $cashCollection = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Total pembayaran transfer — info saja, reset per bulan (tidak perlu disetor)
        $monthStart = Carbon::now()->startOfMonth();
        $transferCollection = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $endDate)
            ->where('payment_method', 'transfer')
            ->sum('amount');

        // Total semua collection
        $totalCollection = $cashCollection + $transferCollection;

        // Pengeluaran yang disetujui — hanya bulan ini (reset tiap bulan)
        $monthStart = Carbon::now()->startOfMonth();
        $approvedExpense = Expense::where('user_id', $collector->id)
            ->where('expense_date', '>=', $monthStart->toDateString())
            ->where('expense_date', '<=', $endDate->toDateString())
            ->where('status', 'approved')
            ->sum('amount');

        // Pengeluaran pending — hanya bulan ini
        $pendingExpense = Expense::where('user_id', $collector->id)
            ->where('expense_date', '>=', $monthStart->toDateString())
            ->where('expense_date', '<=', $endDate->toDateString())
            ->where('status', 'pending')
            ->sum('amount');

        // Komisi penagih (jika ada)
        $commissionRate = $collector->commission_rate ?? 0;
        $commissionAmount = $totalCollection * ($commissionRate / 100);

        // Yang harus disetor = Cash - Pengeluaran disetujui - Komisi
        $mustSettle = $cashCollection - $approvedExpense - $commissionAmount;

        // Hitung jumlah transaksi
        $paymentCount = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->count();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'last_settlement' => $lastSettlement?->period_end?->toDateString(),
            ],
            'total_collection' => $totalCollection,
            'cash_collection' => $cashCollection,
            'transfer_collection' => $transferCollection,
            'approved_expense' => $approvedExpense,
            'pending_expense' => $pendingExpense,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'must_settle' => max(0, $mustSettle),
            'payment_count' => $paymentCount,
        ];
    }

    /**
     * Ambil ringkasan harian penagih
     */
    public function getDailySummary(User $collector, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $payments = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
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
            'settlement' => $this->calculateFinalSettlement($collector, $date->copy()->startOfDay(), $date->copy()->endOfDay()),
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
     * Kirim notifikasi pembayaran ke pelanggan via WhatsApp (queued, 1 pesan/menit)
     *
     * Menggunakan queue dengan delay bertahap untuk menghindari block dari provider WA.
     * Setiap pesan WA dijadwalkan minimal 60 detik setelah pesan terakhir.
     */
    protected function sendPaymentNotification(Customer $customer, array $paymentResult): void
    {
        try {
            $customer->refresh();

            $delaySeconds = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 60);
            $payment = $paymentResult['payment'];
            $messageCount = ($paymentResult['access_opened'] ?? false) ? 2 : 1;

            // Atomic: hitung delay dan reserve slot (mencegah race condition antar penagih)
            $delay = Cache::lock('wa_queue_lock', 5)->block(5, function () use ($delaySeconds, $messageCount) {
                $nextAvailable = (int) Cache::get('wa_queue_next_available', 0);
                $now = now()->timestamp;
                $delay = max(0, $nextAvailable - $now);

                // Reserve slot untuk semua pesan yang akan dikirim
                Cache::put('wa_queue_next_available', max($now, $nextAvailable) + ($delaySeconds * $messageCount), 3600);

                return $delay;
            });

            // Dispatch payment confirmation ke queue dengan delay
            SendNotificationJob::dispatch(
                'whatsapp',
                $customer->phone,
                $this->notificationService->buildPaymentConfirmationMessage($customer, $payment),
                null,
                ['notification_type' => 'payment']
            )->delay(now()->addSeconds($delay));

            // Jika akses dibuka, kirim notifikasi tambahan dengan delay berikutnya
            if ($paymentResult['access_opened'] ?? false) {
                SendNotificationJob::dispatch(
                    'whatsapp',
                    $customer->phone,
                    $this->notificationService->buildAccessOpenedMessage($customer),
                    null,
                    ['notification_type' => 'access_opened']
                )->delay(now()->addSeconds($delay + $delaySeconds));
            }

            Log::info('Payment notification queued', [
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'delay_seconds' => $delay,
                'access_opened' => $paymentResult['access_opened'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue payment notification', [
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
