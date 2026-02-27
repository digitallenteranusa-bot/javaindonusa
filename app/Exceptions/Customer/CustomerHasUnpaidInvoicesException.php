<?php

namespace App\Exceptions\Customer;

use Exception;

class CustomerHasUnpaidInvoicesException extends Exception
{
    public function __construct()
    {
        parent::__construct('Tidak dapat menghapus pelanggan dengan tagihan belum lunas');
    }
}
