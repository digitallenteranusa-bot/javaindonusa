<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Admin\DashboardService;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        DashboardService::clearDashboardCache();
    }

    public function updated(Invoice $invoice): void
    {
        if ($invoice->isDirty('status')) {
            $newStatus = $invoice->status;
            if (in_array($newStatus, ['paid', 'cancelled'])) {
                $invoice->customer?->recalculateTotalDebt();
            }
        }

        DashboardService::clearDashboardCache();
    }

    public function deleted(Invoice $invoice): void
    {
        $invoice->customer?->recalculateTotalDebt();
        DashboardService::clearDashboardCache();
    }
}
