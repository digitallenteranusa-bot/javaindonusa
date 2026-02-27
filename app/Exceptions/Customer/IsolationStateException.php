<?php

namespace App\Exceptions\Customer;

use Exception;

class IsolationStateException extends Exception
{
    public static function alreadyIsolated(): self
    {
        return new self('Pelanggan sudah dalam status isolir.');
    }

    public static function notIsolated(): self
    {
        return new self('Pelanggan tidak dalam status isolir.');
    }
}
