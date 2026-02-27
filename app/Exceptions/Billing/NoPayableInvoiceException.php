<?php

namespace App\Exceptions\Billing;

use Exception;

class NoPayableInvoiceException extends Exception
{
    public function __construct()
    {
        parent::__construct('Tidak ada tagihan yang bisa dibayar');
    }
}
