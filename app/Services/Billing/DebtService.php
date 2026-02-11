<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\DebtHistory;
use Illuminate\Support\Facades\DB;

class DebtService
{
    // ================================================================
    // CORE DEBT OPERATIONS
    // ================================================================

    /**
     * Tambah hutang ke pelanggan
     *
     * @param Customer $customer
     * @param float $amount Jumlah hutang yang ditambahkan
     * @param string $transactionType Jenis transaksi (invoice_added, late_fee, adjustment)
     * @param string|null $referenceType Tipe referensi (invoice, payment, null)
     * @param int|null $referenceId ID referensi
     * @param string|null $description Deskripsi transaksi
     */
    public function addDebt(
        Customer $customer,
        float $amount,
        string $transactionType = 'invoice_added',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null
    ): DebtHistory {
        return DB::transaction(function () use (
            $customer, $amount, $transactionType, $referenceType, $referenceId, $description
        ) {
            $balanceBefore = $customer->total_debt;
            $balanceAfter = $balanceBefore + $amount;

            // Update total hutang customer
            $customer->update(['total_debt' => $balanceAfter]);

            // Tentukan tipe DebtHistory berdasarkan transaction type
            $historyType = $this->mapTransactionToHistoryType($transactionType, 'add');

            // Buat record DebtHistory
            $history = DebtHistory::create([
                'customer_id' => $customer->id,
                'invoice_id' => $referenceType === 'invoice' ? $referenceId : null,
                'payment_id' => $referenceType === 'payment' ? $referenceId : null,
                'type' => $historyType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description ?? $this->getDefaultDescription($transactionType, $amount),
                'reference_number' => $this->getReferenceNumber($referenceType, $referenceId),
                'created_by' => auth()->id(),
            ]);

            return $history;
        });
    }

    /**
     * Kurangi hutang dari pelanggan
     *
     * @param Customer $customer
     * @param float $amount Jumlah hutang yang dikurangi
     * @param string $transactionType Jenis transaksi (payment_received, discount, writeoff)
     * @param string|null $referenceType Tipe referensi (invoice, payment, null)
     * @param int|null $referenceId ID referensi
     * @param string|null $description Deskripsi transaksi
     */
    public function reduceDebt(
        Customer $customer,
        float $amount,
        string $transactionType = 'payment_received',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null
    ): DebtHistory {
        return DB::transaction(function () use (
            $customer, $amount, $transactionType, $referenceType, $referenceId, $description
        ) {
            $balanceBefore = $customer->total_debt;
            $debtReduction = min($amount, $balanceBefore);
            $balanceAfter = max(0, $balanceBefore - $amount);
            $creditAmount = max(0, $amount - $balanceBefore);

            // Update total hutang customer
            $customer->update(['total_debt' => $balanceAfter]);

            // Jika ada kelebihan bayar, simpan sebagai kredit
            if ($creditAmount > 0) {
                $this->addCredit($customer, $creditAmount, $referenceType, $referenceId);
            }

            // Tentukan tipe DebtHistory berdasarkan transaction type
            $historyType = $this->mapTransactionToHistoryType($transactionType, 'reduce');

            // Buat record DebtHistory
            $history = DebtHistory::create([
                'customer_id' => $customer->id,
                'invoice_id' => $referenceType === 'invoice' ? $referenceId : null,
                'payment_id' => $referenceType === 'payment' ? $referenceId : null,
                'type' => $historyType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description ?? $this->getDefaultDescription($transactionType, $amount),
                'reference_number' => $this->getReferenceNumber($referenceType, $referenceId),
                'created_by' => auth()->id(),
            ]);

            return $history;
        });
    }

    // ================================================================
    // SPECIALIZED OPERATIONS
    // ================================================================

    /**
     * Tambah denda keterlambatan
     */
    public function addLateFee(
        Customer $customer,
        Invoice $invoice,
        float $amount,
        ?string $description = null
    ): DebtHistory {
        return $this->addDebt(
            $customer,
            $amount,
            'late_fee',
            'invoice',
            $invoice->id,
            $description ?? "Denda keterlambatan {$invoice->invoice_number}"
        );
    }

    /**
     * Berikan diskon
     */
    public function addDiscount(
        Customer $customer,
        float $amount,
        string $reason,
        ?Invoice $invoice = null
    ): DebtHistory {
        return $this->reduceDebt(
            $customer,
            $amount,
            'discount',
            $invoice ? 'invoice' : null,
            $invoice?->id,
            "Diskon: {$reason}"
        );
    }

    /**
     * Adjustment hutang (bisa tambah atau kurang)
     */
    public function adjustDebt(
        Customer $customer,
        float $amount,
        string $reason
    ): DebtHistory {
        if ($amount >= 0) {
            return $this->addDebt(
                $customer,
                $amount,
                'adjustment_add',
                null,
                null,
                "Penyesuaian: {$reason}"
            );
        } else {
            return $this->reduceDebt(
                $customer,
                abs($amount),
                'adjustment_subtract',
                null,
                null,
                "Penyesuaian: {$reason}"
            );
        }
    }

    /**
     * Write-off hutang (hapus hutang tak tertagih)
     */
    public function writeOffDebt(
        Customer $customer,
        float $amount,
        string $reason
    ): DebtHistory {
        return $this->reduceDebt(
            $customer,
            $amount,
            'writeoff',
            null,
            null,
            "Write-off: {$reason}"
        );
    }

    // ================================================================
    // CREDIT BALANCE OPERATIONS
    // ================================================================

    /**
     * Tambah kredit ke pelanggan (dari kelebihan bayar)
     */
    public function addCredit(
        Customer $customer,
        float $amount,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): DebtHistory {
        $creditBefore = $customer->credit_balance;
        $customer->increment('credit_balance', $amount);

        return DebtHistory::create([
            'customer_id' => $customer->id,
            'invoice_id' => $referenceType === 'invoice' ? $referenceId : null,
            'payment_id' => $referenceType === 'payment' ? $referenceId : null,
            'type' => DebtHistory::TYPE_CREDIT_ADDED,
            'amount' => $amount,
            'balance_before' => $customer->total_debt,
            'balance_after' => $customer->total_debt,
            'description' => "Kelebihan bayar Rp " . number_format($amount, 0, ',', '.') . " disimpan sebagai kredit",
            'reference_number' => $this->getReferenceNumber($referenceType, $referenceId),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Gunakan kredit untuk membayar invoice
     */
    public function useCredit(
        Customer $customer,
        Invoice $invoice,
        float $amount
    ): DebtHistory {
        $creditToUse = min($amount, $customer->credit_balance);

        if ($creditToUse <= 0) {
            return new DebtHistory();
        }

        $balanceBefore = $customer->total_debt;

        // Kurangi kredit
        $customer->decrement('credit_balance', $creditToUse);

        // Apply ke invoice
        $newPaidAmount = $invoice->paid_amount + $creditToUse;
        $newRemaining = $invoice->total_amount - $newPaidAmount;

        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'remaining_amount' => max(0, $newRemaining),
            'status' => $newRemaining <= 0 ? 'paid' : 'partial',
            'paid_at' => $newRemaining <= 0 ? now() : null,
        ]);

        // Kurangi total hutang
        $customer->decrement('total_debt', $creditToUse);
        $balanceAfter = max(0, $balanceBefore - $creditToUse);

        return DebtHistory::create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'type' => DebtHistory::TYPE_CREDIT_USED,
            'amount' => $creditToUse,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Kredit Rp " . number_format($creditToUse, 0, ',', '.') . " digunakan untuk {$invoice->invoice_number}",
            'created_by' => auth()->id(),
        ]);
    }

    // ================================================================
    // QUERY OPERATIONS
    // ================================================================

    /**
     * Ambil ringkasan hutang pelanggan
     */
    public function getDebtSummary(Customer $customer): array
    {
        $invoices = $customer->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        $totalFromInvoices = $invoices->sum('remaining_amount');

        return [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'total_debt' => $customer->total_debt,
            'credit_balance' => $customer->credit_balance,
            'total_from_invoices' => $totalFromInvoices,
            'is_synced' => abs($customer->total_debt - $totalFromInvoices) < 0.01,
            'unpaid_invoices_count' => $invoices->count(),
            'oldest_unpaid' => $invoices->first()?->period_label,
            'newest_unpaid' => $invoices->last()?->period_label,
            'invoices' => $invoices->map(fn($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'period' => $inv->period_label ?? "{$inv->period_month}/{$inv->period_year}",
                'total_amount' => $inv->total_amount,
                'paid_amount' => $inv->paid_amount,
                'remaining_amount' => $inv->remaining_amount,
                'status' => $inv->status,
                'due_date' => $inv->due_date?->format('Y-m-d'),
            ]),
        ];
    }

    /**
     * Ambil riwayat hutang pelanggan
     */
    public function getDebtHistory(
        Customer $customer,
        ?int $limit = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = $customer->debtHistories()
            ->orderBy('created_at', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Recalculate total debt dari invoice (untuk sinkronisasi)
     */
    public function recalculateDebt(Customer $customer, bool $createAdjustment = true): array
    {
        $calculatedDebt = $customer->invoices()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('remaining_amount');

        $currentDebt = $customer->total_debt;
        $difference = $calculatedDebt - $currentDebt;

        if (abs($difference) > 0.01) {
            // Ada perbedaan, update dan catat adjustment jika diminta
            $customer->update(['total_debt' => $calculatedDebt]);

            if ($createAdjustment) {
                DebtHistory::create([
                    'customer_id' => $customer->id,
                    'type' => $difference > 0
                        ? DebtHistory::TYPE_ADJUSTMENT_ADD
                        : DebtHistory::TYPE_ADJUSTMENT_SUBTRACT,
                    'amount' => abs($difference),
                    'balance_before' => $currentDebt,
                    'balance_after' => $calculatedDebt,
                    'description' => 'Penyesuaian otomatis sinkronisasi hutang',
                    'created_by' => auth()->id(),
                ]);
            }

            return [
                'adjusted' => true,
                'previous_debt' => $currentDebt,
                'new_debt' => $calculatedDebt,
                'difference' => $difference,
            ];
        }

        return [
            'adjusted' => false,
            'current_debt' => $currentDebt,
        ];
    }

    /**
     * Bulk recalculate untuk semua pelanggan
     */
    public function bulkRecalculateDebt(bool $createAdjustments = false): array
    {
        $customers = Customer::whereIn('status', ['active', 'isolated'])->get();
        $results = [
            'total' => $customers->count(),
            'adjusted' => 0,
            'unchanged' => 0,
            'details' => [],
        ];

        foreach ($customers as $customer) {
            $result = $this->recalculateDebt($customer, $createAdjustments);

            if ($result['adjusted'] ?? false) {
                $results['adjusted']++;
                $results['details'][] = [
                    'customer_id' => $customer->customer_id,
                    'name' => $customer->name,
                    'previous' => $result['previous_debt'],
                    'new' => $result['new_debt'],
                    'difference' => $result['difference'],
                ];
            } else {
                $results['unchanged']++;
            }
        }

        return $results;
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Map transaction type ke DebtHistory type
     */
    protected function mapTransactionToHistoryType(string $transactionType, string $operation): string
    {
        $mapping = [
            // Add operations
            'invoice_added' => DebtHistory::TYPE_CHARGE,
            'late_fee' => DebtHistory::TYPE_LATE_FEE,
            'adjustment_add' => DebtHistory::TYPE_ADJUSTMENT_ADD,

            // Reduce operations
            'payment_received' => DebtHistory::TYPE_PAYMENT,
            'discount' => DebtHistory::TYPE_DISCOUNT,
            'adjustment_subtract' => DebtHistory::TYPE_ADJUSTMENT_SUBTRACT,
            'writeoff' => DebtHistory::TYPE_WRITEOFF,

            // Credit operations
            'credit_added' => DebtHistory::TYPE_CREDIT_ADDED,
            'credit_used' => DebtHistory::TYPE_CREDIT_USED,
        ];

        return $mapping[$transactionType] ?? ($operation === 'add'
            ? DebtHistory::TYPE_ADJUSTMENT_ADD
            : DebtHistory::TYPE_ADJUSTMENT_SUBTRACT);
    }

    /**
     * Get default description for transaction
     */
    protected function getDefaultDescription(string $transactionType, float $amount): string
    {
        $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');

        return match ($transactionType) {
            'invoice_added' => "Tagihan baru {$formattedAmount}",
            'payment_received' => "Pembayaran {$formattedAmount}",
            'late_fee' => "Denda keterlambatan {$formattedAmount}",
            'discount' => "Diskon {$formattedAmount}",
            'adjustment_add' => "Penambahan hutang {$formattedAmount}",
            'adjustment_subtract' => "Pengurangan hutang {$formattedAmount}",
            'writeoff' => "Write-off {$formattedAmount}",
            'credit_added' => "Kredit ditambahkan {$formattedAmount}",
            'credit_used' => "Kredit digunakan {$formattedAmount}",
            default => "Transaksi hutang {$formattedAmount}",
        };
    }

    /**
     * Get reference number from model
     */
    protected function getReferenceNumber(?string $referenceType, ?int $referenceId): ?string
    {
        if (!$referenceType || !$referenceId) {
            return null;
        }

        return match ($referenceType) {
            'invoice' => Invoice::find($referenceId)?->invoice_number,
            'payment' => Payment::find($referenceId)?->payment_number,
            default => null,
        };
    }
}
