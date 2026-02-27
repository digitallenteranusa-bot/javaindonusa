<?php

namespace App\Exceptions\Billing;

use Exception;

class PaymentCancellationException extends Exception
{
    public static function tooLate(): self
    {
        return new self('Pembayaran hanya dapat dibatalkan dalam 24 jam');
    }

    public static function alreadyCancelled(): self
    {
        return new self('Pembayaran sudah dibatalkan');
    }
}
