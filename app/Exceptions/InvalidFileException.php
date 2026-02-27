<?php

namespace App\Exceptions;

use Exception;

class InvalidFileException extends Exception
{
    public static function invalidType(string $allowed): self
    {
        return new self("Tipe file tidak diizinkan. Hanya file {$allowed} yang diperbolehkan.");
    }

    public static function tooLarge(string $maxSize): self
    {
        return new self("Ukuran file maksimal {$maxSize}.");
    }
}
