<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Admin\DashboardService;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $payment->customer?->update([
            'last_payment_date' => $payment->created_at,
        ]);

        DashboardService::clearDashboardCache();
    }

    public function updated(Payment $payment): void
    {
        if ($payment->isDirty('status') && $payment->status === 'cancelled') {
            $payment->customer?->recalculateTotalDebt();
        }

        DashboardService::clearDashboardCache();
    }
}
