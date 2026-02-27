<?php

namespace App\Exceptions\Collector;

use Exception;

class UnauthorizedCustomerAccessException extends Exception
{
    public function __construct()
    {
        parent::__construct('Anda tidak memiliki akses ke pelanggan ini');
    }
}
