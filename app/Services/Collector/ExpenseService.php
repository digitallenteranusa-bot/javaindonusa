<?php

namespace App\Services\Collector;

use App\Models\User;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    // ================================================================
    // INPUT PENGELUARAN (PETTY CASH)
    // ================================================================

    /**
     * Tambah pengeluaran oleh penagih
     */
    public function createExpense(
        User $collector,
        float $amount,
        string $category,
        string $description,
        ?string $receiptPhoto = null,
        ?Carbon $expenseDate = null
    ): Expense {
        // Validasi batas harian
        $dailyLimit = Setting::getValue('expense', 'daily_limit', 100000);
        $todayTotal = $this->getTodayExpenseTotal($collector);

        if (($todayTotal + $amount) > $dailyLimit) {
            throw new \Exception(
                "Melebihi batas pengeluaran harian. Sisa: Rp " .
                number_format($dailyLimit - $todayTotal, 0, ',', '.')
            );
        }

        return Expense::create([
            'user_id' => $collector->id,
            'amount' => $amount,
            'category' => $category,
            'description' => $description,
            'receipt_photo' => $receiptPhoto,
            'status' => 'pending',
            'expense_date' => $expenseDate ?? Carbon::today(),
        ]);
    }

    /**
     * Upload foto nota
     */
    public function uploadReceipt(User $collector, $file): string
    {
        // Validate file type
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            throw new \Exception('Tipe file tidak diizinkan. Hanya file gambar (JPG, PNG, GIF, WEBP) yang diperbolehkan.');
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('Ukuran file maksimal 5MB.');
        }

        $filename = sprintf(
            'receipts/%d/%s_%s.%s',
            $collector->id,
            Carbon::now()->format('Ymd_His'),
            uniqid(),
            $extension
        );

        Storage::disk('public')->put($filename, file_get_contents($file));

        return $filename;
    }

    /**
     * Ambil total pengeluaran hari ini
     */
    public function getTodayExpenseTotal(User $collector): float
    {
        return Expense::where('user_id', $collector->id)
            ->whereDate('expense_date', Carbon::today())
            ->where('status', '!=', 'rejected')
            ->sum('amount');
    }

    /**
     * Ambil histori pengeluaran penagih
     */
    public function getExpenseHistory(
        User $collector,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $perPage = 20
    ) {
        $query = Expense::where('user_id', $collector->id);

        if ($startDate) {
            $query->whereDate('expense_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('expense_date', '<=', $endDate);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Ambil ringkasan pengeluaran bulan ini
     */
    public function getMonthlyExpenseSummary(User $collector, ?Carbon $month = null): array
    {
        $month = $month ?? Carbon::now();

        $expenses = Expense::where('user_id', $collector->id)
            ->whereYear('expense_date', $month->year)
            ->whereMonth('expense_date', $month->month)
            ->get();

        $byCategory = $expenses->groupBy('category')->map(function ($items) {
            return [
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        });

        $byStatus = $expenses->groupBy('status')->map(function ($items) {
            return [
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        });

        return [
            'month' => $month->format('F Y'),
            'total_expenses' => $expenses->sum('amount'),
            'total_count' => $expenses->count(),
            'approved_total' => $expenses->where('status', 'approved')->sum('amount'),
            'pending_total' => $expenses->where('status', 'pending')->sum('amount'),
            'rejected_total' => $expenses->where('status', 'rejected')->sum('amount'),
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ];
    }

    // ================================================================
    // VERIFIKASI ADMIN
    // ================================================================

    /**
     * Ambil daftar pengeluaran pending untuk verifikasi admin
     */
    public function getPendingExpenses(?int $collectorId = null, int $perPage = 20)
    {
        $query = Expense::where('status', 'pending')
            ->with('user:id,name,phone');

        if ($collectorId) {
            $query->where('user_id', $collectorId);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Approve pengeluaran
     */
    public function approveExpense(Expense $expense, User $admin): Expense
    {
        $expense->update([
            'status' => 'approved',
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        return $expense->fresh();
    }

    /**
     * Reject pengeluaran
     */
    public function rejectExpense(
        Expense $expense,
        User $admin,
        string $reason
    ): Expense {
        $expense->update([
            'status' => 'rejected',
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $expense->fresh();
    }

    // ================================================================
    // SETTLEMENT (SETORAN KE KANTOR)
    // ================================================================

    /**
     * Buat settlement baru
     */
    public function createSettlement(
        User $collector,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $actualAmount,
        ?User $receivedBy = null,
        ?string $notes = null
    ): Settlement {
        return DB::transaction(function () use (
            $collector, $periodStart, $periodEnd, $actualAmount, $receivedBy, $notes
        ) {
            // Hitung expected amount
            $calculation = app(CollectorService::class)->calculateFinalSettlement(
                $collector,
                $periodStart,
                $periodEnd
            );

            $expectedAmount = $calculation['must_settle'];
            $difference = $actualAmount - $expectedAmount;

            // Tentukan status
            $status = 'pending';
            if ($receivedBy) {
                $status = abs($difference) < 1 ? 'settled' : 'discrepancy';
            }

            return Settlement::create([
                'collector_id' => $collector->id,
                'settlement_number' => $this->generateSettlementNumber(),
                'settlement_date' => Carbon::today(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_collection' => $calculation['cash_collection'],
                'total_expense' => $calculation['approved_expense'],
                'commission_amount' => $calculation['commission_amount'],
                'expected_amount' => $expectedAmount,
                'actual_amount' => $actualAmount,
                'difference' => $difference,
                'status' => $status,
                'received_by' => $receivedBy?->id,
                'verified_at' => $receivedBy ? now() : null,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Buat settlement dari data unsettled yang sudah dihitung
     */
    public function createSettlementFromUnsettled(
        User $collector,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $unsettledData,
        float $actualAmount,
        ?string $notes = null
    ): Settlement {
        return DB::transaction(function () use (
            $collector, $periodStart, $periodEnd, $unsettledData, $actualAmount, $notes
        ) {
            $expectedAmount = $unsettledData['must_settle'];
            $difference = $actualAmount - $expectedAmount;

            return Settlement::create([
                'collector_id' => $collector->id,
                'settlement_number' => $this->generateSettlementNumber(),
                'settlement_date' => Carbon::today(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total_collection' => $unsettledData['total_collection'],
                'cash_collection' => $unsettledData['cash_collection'],
                'transfer_collection' => $unsettledData['transfer_collection'],
                'total_expense' => $unsettledData['approved_expense'] + $unsettledData['pending_expense'],
                'approved_expense' => $unsettledData['approved_expense'],
                'commission_rate' => $unsettledData['commission_rate'],
                'commission_amount' => $unsettledData['commission_amount'],
                'expected_amount' => $expectedAmount,
                'actual_amount' => $actualAmount,
                'difference' => $difference,
                'status' => 'pending',
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Verifikasi settlement oleh admin
     */
    public function verifySettlement(
        Settlement $settlement,
        User $admin,
        float $actualAmount,
        ?string $notes = null
    ): Settlement {
        $difference = $actualAmount - $settlement->expected_amount;
        $status = abs($difference) < 1 ? 'settled' : 'discrepancy';

        $settlement->update([
            'actual_amount' => $actualAmount,
            'difference' => $difference,
            'status' => $status,
            'received_by' => $admin->id,
            'verified_at' => now(),
            'notes' => $notes,
        ]);

        return $settlement->fresh();
    }

    /**
     * Ambil histori settlement penagih
     */
    public function getSettlementHistory(
        User $collector,
        int $perPage = 20
    ) {
        return Settlement::where('collector_id', $collector->id)
            ->with('receivedBy:id,name')
            ->orderBy('settlement_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Ambil settlement pending untuk admin
     */
    public function getPendingSettlements(int $perPage = 20)
    {
        return Settlement::where('status', 'pending')
            ->with(['collector:id,name,phone'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Generate nomor settlement
     */
    protected function generateSettlementNumber(): string
    {
        $dateCode = now()->format('Ymd');

        $lastSettlement = Settlement::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastSettlement) {
            preg_match('/(\d+)$/', $lastSettlement->settlement_number, $matches);
            $sequence = intval($matches[1] ?? 0) + 1;
        }

        return sprintf('STL-%s-%05d', $dateCode, $sequence);
    }
}
