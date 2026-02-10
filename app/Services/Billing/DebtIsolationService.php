<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\DebtHistory;
use App\Models\Setting;
use App\Models\BillingLog;
use App\Jobs\IsolateCustomerJob;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class DebtIsolationService
{
    protected MikrotikService $mikrotik;
    protected NotificationService $notification;
    protected DebtService $debtService;

    public function __construct(
        MikrotikService $mikrotik,
        NotificationService $notification,
        DebtService $debtService
    ) {
        $this->mikrotik = $mikrotik;
        $this->notification = $notification;
        $this->debtService = $debtService;
    }

    // ================================================================
    // LOGIKA PENAMBAHAN HUTANG SETIAP TANGGAL 1
    // ================================================================

    /**
     * Proses penambahan hutang bulanan untuk semua pelanggan
     * Dijalankan setiap tanggal 1 via scheduler
     */
    public function processMonthlyDebtAddition(): array
    {
        $now = Carbon::now();
        $periodMonth = $now->month;
        $periodYear = $now->year;

        $results = [
            'processed' => 0,
            'debt_added' => 0,
            'skipped_paid' => 0,
            'skipped_terminated' => 0,
            'errors' => [],
        ];

        // Ambil semua pelanggan aktif dan terisolir
        $customers = Customer::whereIn('status', ['active', 'isolated'])
            ->whereNull('deleted_at')
            ->with('package')
            ->get();

        foreach ($customers as $customer) {
            try {
                $result = $this->addMonthlyDebtForCustomer($customer, $periodMonth, $periodYear);

                if ($result['added']) {
                    $results['debt_added']++;
                } else {
                    $results['skipped_paid']++;
                }

                $results['processed']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Log hasil proses
        BillingLog::logSystem(
            BillingLog::ACTION_BILLING_RUN,
            "Proses penambahan hutang bulanan periode {$periodMonth}/{$periodYear}",
            $results
        );

        return $results;
    }

    /**
     * Tambah hutang bulanan untuk satu pelanggan
     */
    public function addMonthlyDebtForCustomer(
        Customer $customer,
        int $periodMonth,
        int $periodYear
    ): array {
        // Cek apakah invoice bulan ini sudah ada
        $existingInvoice = Invoice::where('customer_id', $customer->id)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->first();

        if ($existingInvoice) {
            // Invoice sudah ada, cek status
            if ($existingInvoice->status === 'paid') {
                return ['added' => false, 'reason' => 'already_paid'];
            }
            // Invoice ada tapi belum bayar, tidak perlu tambah lagi
            return ['added' => false, 'reason' => 'invoice_exists'];
        }

        // Buat invoice baru dan tambahkan ke hutang
        return DB::transaction(function () use ($customer, $periodMonth, $periodYear) {
            $package = $customer->package;
            $amount = $package->price;

            // Hitung periode
            $periodStart = Carbon::create($periodYear, $periodMonth, 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $dueDay = (int) Setting::getValue('billing', 'billing_due_date', 20);
            // Pastikan tanggal jatuh tempo tidak melebihi akhir bulan
            $dueDay = min($dueDay, $periodEnd->day);
            $dueDate = Carbon::create($periodYear, $periodMonth, $dueDay);

            // Generate invoice
            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber($periodYear, $periodMonth),
                'customer_id' => $customer->id,
                'period_month' => $periodMonth,
                'period_year' => $periodYear,
                'package_name' => $package->name,
                'package_price' => $amount,
                'additional_charges' => 0,
                'discount' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_amount' => $amount,
                'status' => 'pending',
                'due_date' => $dueDate,
            ]);

            // Tambahkan ke total hutang pelanggan
            $this->debtService->addDebt(
                $customer,
                $amount,
                'invoice_added',
                'invoice',
                $invoice->id,
                "Tagihan {$package->name} periode {$periodMonth}/{$periodYear}"
            );

            return [
                'added' => true,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ];
        });
    }

    // ================================================================
    // LOGIKA PEMBAYARAN & PENGURANGAN HUTANG
    // ================================================================

    /**
     * Proses pembayaran (partial/full) dan kurangi hutang
     */
    public function processPayment(
        Customer $customer,
        float $amount,
        string $paymentMethod = 'cash',
        ?int $collectorId = null,
        ?string $transferProof = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use (
            $customer, $amount, $paymentMethod, $collectorId, $transferProof, $notes
        ) {
            $previousDebt = $customer->total_debt;
            $allocations = [];
            $remainingPayment = $amount;

            // 1. Alokasi ke invoice tertua (FIFO)
            $unpaidInvoices = Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('period_year', 'asc')
                ->orderBy('period_month', 'asc')
                ->get();

            foreach ($unpaidInvoices as $invoice) {
                if ($remainingPayment <= 0) break;

                $payForInvoice = min($remainingPayment, $invoice->remaining_amount);
                $newPaidAmount = $invoice->paid_amount + $payForInvoice;
                $newRemaining = $invoice->total_amount - $newPaidAmount;

                // Update invoice
                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => max(0, $newRemaining),
                    'status' => $newRemaining <= 0 ? 'paid' : 'partial',
                    'paid_at' => $newRemaining <= 0 ? now() : null,
                ]);

                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $payForInvoice,
                    'status' => $newRemaining <= 0 ? 'paid' : 'partial',
                ];

                $remainingPayment -= $payForInvoice;
            }

            // 2. Buat record pembayaran
            $payment = \App\Models\Payment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'customer_id' => $customer->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_channel' => $paymentMethod === 'transfer' ? 'bank' : 'office',
                'transfer_proof' => $transferProof,
                'allocated_to_invoice' => $amount - $remainingPayment,
                'allocated_to_debt' => $remainingPayment,
                'allocated_invoices' => $allocations,
                'collector_id' => $collectorId,
                'received_by' => auth()->id(),
                'notes' => $notes,
            ]);

            // 2b. Sync alokasi ke pivot table invoice_payment
            if (!empty($allocations)) {
                $syncData = [];
                foreach ($allocations as $alloc) {
                    $syncData[$alloc['invoice_id']] = ['amount' => $alloc['amount']];
                }
                $payment->invoices()->sync($syncData);
            }

            // 3. Kurangi total hutang
            $this->debtService->reduceDebt(
                $customer,
                $amount,
                'payment_received',
                'payment',
                $payment->id,
                "Pembayaran {$payment->payment_number}"
            );

            // 4. Update tanggal pembayaran terakhir
            $customer->update(['last_payment_date' => now()]);

            // 5. Cek dan buka isolir jika memenuhi syarat
            $accessOpened = $this->checkAndOpenAccess($customer);

            // 6. Log aktivitas
            BillingLog::logPayment(
                $payment,
                BillingLog::ACTION_PAYMENT_RECEIVED,
                "Pembayaran Rp " . number_format($amount, 0, ',', '.') . " dari {$customer->name}"
            );

            $customer->refresh();

            return [
                'success' => true,
                'payment' => $payment,
                'previous_debt' => $previousDebt,
                'new_debt' => $customer->total_debt,
                'allocations' => $allocations,
                'access_opened' => $accessOpened,
            ];
        });
    }

    // ================================================================
    // LOGIKA ISOLIR OTOMATIS
    // ================================================================

    /**
     * Cek dan proses isolir untuk semua pelanggan
     * Dijalankan setiap hari via scheduler
     *
     * Pelanggan rapel dikecualikan dari isolasi otomatis
     */
    public function checkAndProcessIsolation(): array
    {
        $overdueMonths = (int) Setting::getValue('isolation', 'isolation_threshold_months', 3);
        $graceDays = (int) Setting::getValue('billing', 'billing_grace_days', 7);

        $results = [
            'checked' => 0,
            'isolated' => 0,
            'skipped_rapel' => 0,
            'skipped_recent_payment' => 0,
            'errors' => [],
        ];

        // Ambil pelanggan aktif dengan hutang
        $customers = Customer::where('status', 'active')
            ->where('total_debt', '>', 0)
            ->whereNull('deleted_at')
            ->with(['invoices' => function ($query) {
                $query->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('period_year', 'asc')
                    ->orderBy('period_month', 'asc');
            }])
            ->get();

        foreach ($customers as $customer) {
            try {
                $results['checked']++;

                // Cek apakah harus diisolir
                $shouldIsolate = $this->shouldIsolateCustomer(
                    $customer,
                    $overdueMonths,
                    $graceDays
                );

                if ($shouldIsolate['isolate']) {
                    // Dispatch job isolir (parameter: customerId, sendNotification)
                    IsolateCustomerJob::dispatch($customer->id, true);
                    $results['isolated']++;
                } else {
                    if ($shouldIsolate['reason'] === 'rapel_customer') {
                        $results['skipped_rapel']++;
                    } elseif ($shouldIsolate['reason'] === 'recent_payment') {
                        $results['skipped_recent_payment']++;
                    }
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Tentukan apakah pelanggan harus diisolir
     * Dengan pengecualian untuk pelanggan rapel
     *
     * Pelanggan dengan tipe pembayaran rapel (is_rapel=true atau payment_behavior='rapel')
     * akan mendapat toleransi khusus sesuai setting rapel_tolerance_months
     */
    public function shouldIsolateCustomer(
        Customer $customer,
        int $overdueMonths = 3,
        int $graceDays = 7
    ): array {
        $now = Carbon::now();

        // 1. CEK PENGECUALIAN: Pelanggan dengan kebiasaan bayar rapel
        // Cek via is_rapel (legacy) atau payment_behavior (new)
        $isRapelCustomer = $customer->is_rapel || $customer->payment_behavior === 'rapel';

        if ($isRapelCustomer) {
            // Prioritas: customer setting > global setting > default 3 bulan
            $globalRapelMonths = (int) Setting::getValue('isolation', 'rapel_tolerance_months', 3);
            $rapelMonths = $customer->rapel_months ?: $globalRapelMonths;

            // Hitung jumlah invoice belum bayar
            $unpaidCount = $customer->invoices
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->count();

            // Jika masih dalam batas rapel, JANGAN ISOLIR
            if ($unpaidCount <= $rapelMonths) {
                return [
                    'isolate' => false,
                    'reason' => 'rapel_customer',
                    'message' => "Pelanggan rapel dikecualikan dari isolasi, hutang {$unpaidCount} bulan (batas: {$rapelMonths})",
                ];
            }
        }

        // 2. CEK PENGECUALIAN: Ada pembayaran dalam X hari terakhir (dari setting)
        $recentPaymentDays = (int) Setting::getValue('isolation', 'recent_payment_days', 30);

        if ($customer->last_payment_date) {
            $daysSincePayment = $customer->last_payment_date->diffInDays($now);

            if ($daysSincePayment <= $recentPaymentDays) {
                return [
                    'isolate' => false,
                    'reason' => 'recent_payment',
                    'message' => "Ada pembayaran {$daysSincePayment} hari lalu (toleransi: {$recentPaymentDays} hari)",
                ];
            }
        }

        // 3. Hitung invoice overdue yang melewati grace period
        $overdueInvoices = $customer->invoices->filter(function ($invoice) use ($now, $graceDays) {
            if (!in_array($invoice->status, ['pending', 'partial', 'overdue'])) {
                return false;
            }

            $dueDate = Carbon::parse($invoice->due_date);
            $gracePeriodEnd = $dueDate->copy()->addDays($graceDays);

            return $now->isAfter($gracePeriodEnd);
        });

        // 4. Cek apakah ada invoice berturut-turut yang overdue
        $consecutiveOverdue = $this->countConsecutiveOverdueMonths($overdueInvoices);

        if ($consecutiveOverdue >= $overdueMonths) {
            return [
                'isolate' => true,
                'reason' => "Tunggakan {$consecutiveOverdue} bulan berturut-turut",
                'overdue_months' => $consecutiveOverdue,
            ];
        }

        return [
            'isolate' => false,
            'reason' => 'not_overdue_enough',
            'overdue_months' => $consecutiveOverdue,
        ];
    }

    /**
     * Hitung bulan berturut-turut yang overdue
     */
    protected function countConsecutiveOverdueMonths(Collection $invoices): int
    {
        if ($invoices->isEmpty()) {
            return 0;
        }

        // Urutkan berdasarkan periode terbaru
        $sorted = $invoices->sortByDesc(function ($invoice) {
            return $invoice->period_year * 100 + $invoice->period_month;
        });

        $consecutive = 0;
        $previousPeriod = null;

        foreach ($sorted as $invoice) {
            $currentPeriod = Carbon::create($invoice->period_year, $invoice->period_month, 1);

            if ($previousPeriod === null) {
                $consecutive = 1;
                $previousPeriod = $currentPeriod;
                continue;
            }

            // Cek apakah bulan sebelumnya
            $expectedPrevious = $previousPeriod->copy()->subMonth();

            if ($currentPeriod->isSameMonth($expectedPrevious)) {
                $consecutive++;
                $previousPeriod = $currentPeriod;
            } else {
                break; // Tidak berturut-turut
            }
        }

        return $consecutive;
    }

    // ================================================================
    // EKSEKUSI ISOLIR KE MIKROTIK
    // ================================================================

    /**
     * Isolir pelanggan - pindahkan ke Address List ISOLIR
     */
    public function isolateCustomer(Customer $customer, string $reason): array
    {
        try {
            // 1. Koneksi ke Mikrotik
            $this->mikrotik->connect($customer->router);

            // Tentukan tipe koneksi (default pppoe jika tidak diset)
            $connectionType = $customer->connection_type ?? 'pppoe';

            // 2. Tambahkan ke address list ISOLIR
            // Untuk semua tipe, gunakan IP address
            $ipAddress = $customer->static_ip ?? $customer->ip_address;
            if ($ipAddress) {
                $result = $this->mikrotik->addToAddressList(
                    $ipAddress,
                    'ISOLIR',
                    "Auto isolir: {$reason}"
                );
            } else {
                $result = ['success' => true, 'message' => 'No IP to isolate'];
            }

            // 3. Ubah profile ke isolated (bandwidth minimal) dan disconnect
            if ($connectionType === 'pppoe' && $customer->pppoe_username) {
                $this->mikrotik->changePPPoEProfile(
                    $customer->pppoe_username,
                    config('mikrotik.isolation.profile', 'isolir')
                );
                // Disconnect session agar reconnect dengan profile baru
                $this->mikrotik->disconnectPPPoE($customer->pppoe_username);
            }

            // 4. Update status customer
            $customer->update([
                'status' => 'isolated',
                'isolation_reason' => $reason,
            ]);

            // 5. Kirim notifikasi
            $this->notification->sendIsolationNotice($customer, $reason);

            // 6. Log aktivitas
            BillingLog::logCustomer(
                $customer,
                BillingLog::ACTION_CUSTOMER_ISOLATED,
                "Pelanggan diisolir: {$reason}"
            );

            return [
                'success' => true,
                'message' => 'Pelanggan berhasil diisolir',
            ];

        } catch (\Exception $e) {
            BillingLog::logCustomer(
                $customer,
                BillingLog::ACTION_CUSTOMER_ISOLATED,
                "Gagal isolir: " . $e->getMessage()
            );

            throw $e;
        }
    }

    // ================================================================
    // BUKA AKSES ISOLIR
    // ================================================================

    /**
     * Cek dan buka akses jika memenuhi syarat
     */
    public function checkAndOpenAccess(Customer $customer): bool
    {
        // Hanya proses jika status isolated
        if ($customer->status !== 'isolated') {
            return false;
        }

        // Cek apakah masih ada invoice overdue
        $hasOverdue = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['overdue'])
            ->exists();

        // Cek apakah ada pembayaran dalam 24 jam terakhir
        $hasRecentPayment = \App\Models\Payment::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        // Buka akses jika tidak ada overdue ATAU ada pembayaran baru
        if (!$hasOverdue || $hasRecentPayment) {
            return $this->openAccess($customer);
        }

        return false;
    }

    /**
     * Buka akses pelanggan
     */
    public function openAccess(Customer $customer): bool
    {
        try {
            // 1. Koneksi ke Mikrotik
            $this->mikrotik->connect($customer->router);

            // Tentukan tipe koneksi (default pppoe jika tidak diset)
            $connectionType = $customer->connection_type ?? 'pppoe';

            // 2. Hapus dari address list ISOLIR
            $ipAddress = $customer->static_ip ?? $customer->ip_address;
            if ($ipAddress) {
                $this->mikrotik->removeFromAddressList($ipAddress, 'ISOLIR');
            }

            // 3. Kembalikan profile normal
            if ($connectionType === 'pppoe' && $customer->package && $customer->pppoe_username) {
                $profileName = $customer->package->mikrotik_profile ?? 'default';
                $this->mikrotik->changePPPoEProfile($customer->pppoe_username, $profileName);
            }

            // 4. Update status customer
            $customer->update([
                'status' => 'active',
                'isolation_reason' => null,
            ]);

            // 5. Kirim notifikasi
            $this->notification->sendAccessOpenedNotice($customer);

            // 6. Log aktivitas
            BillingLog::logCustomer(
                $customer,
                BillingLog::ACTION_CUSTOMER_REOPENED,
                'Isolir dibuka setelah pembayaran'
            );

            return true;

        } catch (\Exception $e) {
            BillingLog::logCustomer(
                $customer,
                BillingLog::ACTION_CUSTOMER_REOPENED,
                "Gagal buka akses: " . $e->getMessage()
            );

            return false;
        }
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    protected function generateInvoiceNumber(int $year, int $month): string
    {
        $prefix = Setting::getValue('billing', 'invoice_prefix', 'INV');
        $periodCode = sprintf('%04d%02d', $year, $month);

        $lastInvoice = Invoice::where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastInvoice) {
            preg_match('/(\d+)$/', $lastInvoice->invoice_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $periodCode, $sequence);
    }

    protected function generatePaymentNumber(): string
    {
        $prefix = Setting::getValue('billing', 'payment_prefix', 'PAY');
        $dateCode = now()->format('Ymd');

        $lastPayment = \App\Models\Payment::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastPayment) {
            preg_match('/(\d+)$/', $lastPayment->payment_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $dateCode, $sequence);
    }
}
