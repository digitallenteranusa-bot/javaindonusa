<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Jobs\ReopenCustomerJob;
use App\Models\Invoice;

class CheckAndReopenCustomer
{
    public function handle(PaymentReceived $event): void
    {
        $customer = $event->customer;
        $customer->refresh();

        // If customer is isolated and has no overdue invoices, reopen
        if ($customer->status === 'isolated') {
            $hasOverdue = Invoice::where('customer_id', $customer->id)
                ->where('status', 'overdue')
                ->exists();

            if (!$hasOverdue || $customer->total_debt <= 0) {
                dispatch(new ReopenCustomerJob($customer->id));
            }
        }
    }
}
