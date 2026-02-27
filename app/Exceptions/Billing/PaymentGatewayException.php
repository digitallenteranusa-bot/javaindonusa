<?php

namespace App\Exceptions\Billing;

use Exception;

class PaymentGatewayException extends Exception
{
    protected string $gateway;

    public function __construct(string $gateway, string $message)
    {
        $this->gateway = $gateway;
        parent::__construct("Gagal membuat transaksi: {$message}");
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }
}
