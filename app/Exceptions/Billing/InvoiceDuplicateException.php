<?php

namespace App\Exceptions\Billing;

use Exception;

class InvoiceDuplicateException extends Exception
{
    public function __construct(int $month, int $year, string $invoiceNumber)
    {
        parent::__construct("Invoice untuk periode {$month}/{$year} sudah ada (#{$invoiceNumber})");
    }
}
