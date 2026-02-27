<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if ($invoice->isDirty('status')) {
            $newStatus = $invoice->status;
            if (in_array($newStatus, ['paid', 'cancelled'])) {
                $invoice->customer?->recalculateTotalDebt();
            }
        }
    }

    public function deleted(Invoice $invoice): void
    {
        $invoice->customer?->recalculateTotalDebt();
    }
}
