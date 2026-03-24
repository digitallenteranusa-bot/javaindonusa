<?php

namespace App\Services\Billing;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditNoteService
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Create a credit note (pending approval)
     */
    public function create(
        Customer $customer,
        float $amount,
        string $type,
        string $reason,
        ?Invoice $invoice = null,
        ?Payment $payment = null,
        ?string $notes = null
    ): CreditNote {
        return CreditNote::create([
            'credit_note_number' => $this->generateNumber(),
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment?->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'status' => CreditNote::STATUS_PENDING,
            'created_by' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    /**
     * Approve a credit note — applies the credit/refund to customer
     */
    public function approve(CreditNote $creditNote, User $approver): CreditNote
    {
        if ($creditNote->status !== CreditNote::STATUS_PENDING) {
            throw new \RuntimeException('Credit note sudah diproses');
        }

        return DB::transaction(function () use ($creditNote, $approver) {
            $customer = $creditNote->customer;

            // Apply based on type
            if ($creditNote->type === CreditNote::TYPE_REFUND) {
                // Refund: reduce debt
                $this->debtService->reduceDebt(
                    $customer,
                    $creditNote->amount,
                    "Refund #{$creditNote->credit_note_number}: {$creditNote->reason}"
                );
            } elseif ($creditNote->type === CreditNote::TYPE_CREDIT) {
                // Credit note: add to customer credit balance
                $customer->increment('credit_balance', $creditNote->amount);

                $this->debtService->reduceDebt(
                    $customer,
                    $creditNote->amount,
                    "Credit Note #{$creditNote->credit_note_number}: {$creditNote->reason}"
                );
            } elseif ($creditNote->type === CreditNote::TYPE_ADJUSTMENT) {
                // Adjustment: reduce debt directly
                $this->debtService->reduceDebt(
                    $customer,
                    $creditNote->amount,
                    "Penyesuaian #{$creditNote->credit_note_number}: {$creditNote->reason}"
                );
            }

            $creditNote->update([
                'status' => CreditNote::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            Log::info('Credit note approved', [
                'credit_note_id' => $creditNote->id,
                'customer_id' => $customer->id,
                'amount' => $creditNote->amount,
                'type' => $creditNote->type,
                'approved_by' => $approver->id,
            ]);

            return $creditNote->fresh();
        });
    }

    /**
     * Reject a credit note
     */
    public function reject(CreditNote $creditNote, User $rejector, string $rejectReason): CreditNote
    {
        if ($creditNote->status !== CreditNote::STATUS_PENDING) {
            throw new \RuntimeException('Credit note sudah diproses');
        }

        $creditNote->update([
            'status' => CreditNote::STATUS_REJECTED,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'notes' => ($creditNote->notes ? $creditNote->notes . "\n" : '') . "[Ditolak] {$rejectReason}",
        ]);

        return $creditNote->fresh();
    }

    /**
     * Generate unique credit note number
     */
    protected function generateNumber(): string
    {
        $prefix = 'CN';
        $dateCode = now()->format('Ymd');

        $lastCn = CreditNote::where('credit_note_number', 'like', "{$prefix}-{$dateCode}-%")
            ->lockForUpdate()
            ->orderBy('credit_note_number', 'desc')
            ->first();

        if ($lastCn) {
            $lastNumber = (int) substr($lastCn->credit_note_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $dateCode, $newNumber);
    }
}
