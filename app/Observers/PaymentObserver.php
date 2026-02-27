<?php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $payment->customer?->update([
            'last_payment_date' => $payment->created_at,
        ]);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->isDirty('status') && $payment->status === 'cancelled') {
            $payment->customer?->recalculateTotalDebt();
        }
    }
}
