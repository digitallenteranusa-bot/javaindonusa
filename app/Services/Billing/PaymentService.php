<?php

namespace App\Services\Billing;

use App\Events\PaymentReceived;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Billing\PaymentCancellationException;
use Carbon\Carbon;

class PaymentService
{
    protected DebtService $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Process a new payment
     */
    public function processPayment(
        Customer $customer,
        float $amount,
        string $paymentMethod = 'cash',
        ?User $collector = null,
        ?User $receivedBy = null,
        ?string $transferProof = null,
        ?string $notes = null,
        ?string $paymentChannel = null,
        ?string $referenceNumber = null
    ): Payment {
        return DB::transaction(function () use ($customer, $amount, $paymentMethod, $collector, $receivedBy, $transferProof, $notes, $paymentChannel, $referenceNumber) {
            // Determine payment channel
            $channel = $paymentChannel ?? ($collector ? 'collector' : 'office');

            // Create payment record
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'payment_number' => $this->generatePaymentNumber(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_channel' => $channel,
                'collector_id' => $collector?->id,
                'received_by' => $receivedBy?->id ?? auth()->id(),
                'transfer_proof' => $transferProof,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'status' => 'verified',
            ]);

            // Allocate payment to invoices (FIFO - oldest first)
            $allocation = $this->allocatePaymentToInvoices($customer, $amount, $payment);

            // Update payment with allocation info
            $payment->update([
                'allocated_to_invoice' => $allocation['invoice_total'],
                'allocated_to_debt' => $allocation['debt_total'],
            ]);

            // Reduce customer debt
            $this->debtService->reduceDebt(
                $customer,
                $amount,
                "Payment #{$payment->payment_number}"
            );

            // Dispatch event for reopen check and other side effects
            $freshPayment = $payment->fresh(['customer', 'invoices', 'collector']);
            PaymentReceived::dispatch($customer, $freshPayment);

            return $freshPayment;
        });
    }

    /**
     * Allocate payment to invoices using FIFO method
     */
    protected function allocatePaymentToInvoices(Customer $customer, float $amount, Payment $payment): array
    {
        $remainingAmount = $amount;
        $invoiceTotal = 0;
        $debtTotal = 0;

        // Get unpaid invoices ordered by oldest first
        $unpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        foreach ($unpaidInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceRemaining = $invoice->remaining_amount;
            $allocationAmount = min($remainingAmount, $invoiceRemaining);

            if ($allocationAmount > 0) {
                // Create payment-invoice relation
                $payment->invoices()->attach($invoice->id, [
                    'amount' => $allocationAmount,
                ]);

                // Update invoice
                $newPaidAmount = $invoice->paid_amount + $allocationAmount;
                $newRemainingAmount = $invoice->total_amount - $newPaidAmount;

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => $newRemainingAmount,
                    'status' => $newRemainingAmount <= 0 ? 'paid' : 'partial',
                    'paid_at' => $newRemainingAmount <= 0 ? now() : null,
                ]);

                $invoiceTotal += $allocationAmount;
                $remainingAmount -= $allocationAmount;
            }
        }

        // Any remaining amount goes to general debt reduction
        if ($remainingAmount > 0) {
            $debtTotal = $remainingAmount;
        }

        return [
            'invoice_total' => $invoiceTotal,
            'debt_total' => $debtTotal,
        ];
    }

    /**
     * Cancel a payment
     */
    public function cancelPayment(Payment $payment, string $reason): Payment
    {
        $payment->load(['invoices', 'customer']);

        return DB::transaction(function () use ($payment, $reason) {
            if ($payment->status === 'cancelled') {
                throw PaymentCancellationException::alreadyCancelled();
            }

            // Reverse invoice allocations
            foreach ($payment->invoices as $invoice) {
                $allocationAmount = $invoice->pivot->amount ?? 0;
                if ($allocationAmount <= 0) {
                    continue;
                }

                $newPaidAmount = max(0, $invoice->paid_amount - $allocationAmount);
                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => $invoice->total_amount - $newPaidAmount,
                    'status' => $newPaidAmount <= 0 ? 'pending' : 'partial',
                    'paid_at' => null,
                ]);
            }

            // Detach all invoice relations
            $payment->invoices()->detach();

            // Add back to debt
            if ($payment->customer) {
                $this->debtService->addDebt(
                    $payment->customer,
                    $payment->amount,
                    'adjustment_add',
                    'payment',
                    $payment->id,
                    "Pembatalan pembayaran #{$payment->payment_number}: {$reason}"
                );
            }

            // Update payment status
            $payment->update([
                'status' => 'cancelled',
                'notes' => $reason,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Generate unique payment number with locking
     */
    protected function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $dateCode = now()->format('Ymd');

        // Use database lock to prevent race condition
        $lastPayment = Payment::where('payment_number', 'like', "{$prefix}-{$dateCode}-%")
            ->lockForUpdate()
            ->orderBy('payment_number', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = (int) substr($lastPayment->payment_number, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $dateCode, $newNumber);
    }

    /**
     * Get payment statistics
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::where('status', 'verified');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $payments = $query->get();

        return [
            'total_count' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'cash' => $payments->where('payment_method', 'cash')->sum('amount'),
            'transfer' => $payments->where('payment_method', 'transfer')->sum('amount'),
            'by_collector' => $payments->whereNotNull('collector_id')->sum('amount'),
            'by_admin' => $payments->whereNull('collector_id')->sum('amount'),
        ];
    }

    /**
     * Get daily collection summary for a collector
     */
    public function getDailyCollectionSummary(User $collector, ?Carbon $date = null): array
    {
        $date = $date ?? now();

        $payments = Payment::where('collector_id', $collector->id)
            ->where('status', 'verified')
            ->whereDate('created_at', $date)
            ->get();

        return [
            'total' => $payments->sum('amount'),
            'cash' => $payments->where('payment_method', 'cash')->sum('amount'),
            'transfer' => $payments->where('payment_method', 'transfer')->sum('amount'),
            'count' => $payments->count(),
        ];
    }

    /**
     * Get payments for a customer
     */
    public function getCustomerPayments(Customer $customer, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('customer_id', $customer->id)
            ->where('status', 'verified')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
