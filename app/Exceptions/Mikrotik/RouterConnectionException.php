<?php

namespace App\Exceptions\Mikrotik;

use Exception;

class RouterConnectionException extends Exception
{
    public static function connectionFailed(string $error, int $errno = 0): self
    {
        return new self("Connection failed: {$error} ({$errno})");
    }

    public static function loginFailed(): self
    {
        return new self('Login failed: Invalid credentials');
    }

    public static function notConnected(): self
    {
        return new self('Not connected to router');
    }
}
