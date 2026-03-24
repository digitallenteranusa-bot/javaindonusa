<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentPlanService
{
    /**
     * Create a payment plan for a customer with outstanding debt.
     * Does NOT create new debt — this just restructures how existing debt is paid.
     */
    public function createPlan(
        Customer $customer,
        int $installmentCount,
        ?float $totalAmount = null,
        ?string $notes = null
    ): PaymentPlan {
        // Default to customer's current total debt
        $totalDebtAmount = $totalAmount ?? (float) $customer->total_debt;

        if ($totalDebtAmount <= 0) {
            throw new \RuntimeException('Pelanggan tidak memiliki hutang untuk dibuat cicilan.');
        }

        if ($installmentCount < 2 || $installmentCount > 24) {
            throw new \RuntimeException('Jumlah cicilan harus antara 2-24 bulan.');
        }

        $installmentAmount = ceil($totalDebtAmount / $installmentCount);

        return DB::transaction(function () use ($customer, $totalDebtAmount, $installmentCount, $installmentAmount, $notes) {
            $startDate = now()->startOfMonth()->addMonth();

            $plan = PaymentPlan::create([
                'customer_id' => $customer->id,
                'total_debt_amount' => $totalDebtAmount,
                'installment_count' => $installmentCount,
                'installment_amount' => $installmentAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalDebtAmount,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addMonths($installmentCount - 1),
                'status' => PaymentPlan::STATUS_ACTIVE,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create installment schedule
            $remaining = $totalDebtAmount;
            for ($i = 1; $i <= $installmentCount; $i++) {
                // Last installment gets the remainder to handle rounding
                $amount = ($i === $installmentCount) ? $remaining : $installmentAmount;
                $remaining -= $amount;

                PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'installment_number' => $i,
                    'amount' => $amount,
                    'due_date' => $startDate->copy()->addMonths($i - 1)->day(20), // Due on 20th
                    'status' => PaymentPlanInstallment::STATUS_PENDING,
                ]);
            }

            Log::info('Payment plan created', [
                'plan_id' => $plan->id,
                'customer_id' => $customer->id,
                'total' => $totalDebtAmount,
                'installments' => $installmentCount,
            ]);

            return $plan->load('installments');
        });
    }

    /**
     * Record a payment against a payment plan installment
     */
    public function recordInstallmentPayment(PaymentPlanInstallment $installment, Payment $payment): void
    {
        DB::transaction(function () use ($installment, $payment) {
            $plan = $installment->paymentPlan;

            $installment->update([
                'paid_amount' => $payment->amount,
                'status' => $payment->amount >= $installment->amount
                    ? PaymentPlanInstallment::STATUS_PAID
                    : PaymentPlanInstallment::STATUS_PARTIAL,
                'payment_id' => $payment->id,
                'paid_at' => now(),
            ]);

            // Update plan totals
            $totalPaid = $plan->installments()->sum('paid_amount');
            $plan->update([
                'paid_amount' => $totalPaid,
                'remaining_amount' => max(0, $plan->total_debt_amount - $totalPaid),
            ]);

            // Check if plan is completed
            $allPaid = $plan->installments()
                ->where('status', '!=', PaymentPlanInstallment::STATUS_PAID)
                ->doesntExist();

            if ($allPaid) {
                $plan->update(['status' => PaymentPlan::STATUS_COMPLETED]);
            }
        });
    }

    /**
     * Cancel a payment plan
     */
    public function cancelPlan(PaymentPlan $plan, string $reason): PaymentPlan
    {
        if ($plan->status !== PaymentPlan::STATUS_ACTIVE) {
            throw new \RuntimeException('Hanya plan aktif yang bisa dibatalkan.');
        }

        $plan->update([
            'status' => PaymentPlan::STATUS_CANCELLED,
            'notes' => ($plan->notes ? $plan->notes . "\n" : '') . "[Dibatalkan] {$reason}",
        ]);

        // Mark remaining unpaid installments as cancelled
        $plan->installments()
            ->where('status', PaymentPlanInstallment::STATUS_PENDING)
            ->update(['status' => 'overdue']);

        return $plan->fresh('installments');
    }

    /**
     * Update overdue installments
     */
    public function updateOverdueInstallments(): int
    {
        return PaymentPlanInstallment::where('status', PaymentPlanInstallment::STATUS_PENDING)
            ->where('due_date', '<', now())
            ->update(['status' => PaymentPlanInstallment::STATUS_OVERDUE]);
    }
}
