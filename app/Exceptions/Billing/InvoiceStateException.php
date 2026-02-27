<?php

namespace App\Exceptions\Billing;

use Exception;

class InvoiceStateException extends Exception
{
    public static function cannotCancel(): self
    {
        return new self('Hanya invoice pending yang dapat dibatalkan');
    }

    public static function alreadyPaid(): self
    {
        return new self('Invoice yang sudah lunas tidak dapat diubah');
    }
}
