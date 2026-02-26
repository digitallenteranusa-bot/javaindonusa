<?php

namespace App\Listeners;

use App\Events\InvoiceGenerated;
use App\Models\BillingLog;

class LogInvoiceGeneration
{
    public function handle(InvoiceGenerated $event): void
    {
        BillingLog::logSystem('invoice_generation', "Generated invoices for {$event->month}/{$event->year}", [
            'month' => $event->month,
            'year' => $event->year,
            'generated' => $event->generated,
            'skipped' => $event->skipped,
            'errors' => $event->errors,
        ]);
    }
}
