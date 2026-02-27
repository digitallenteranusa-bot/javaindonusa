<?php

namespace App\Exceptions\Mikrotik;

use Exception;

class RouterNotAssignedException extends Exception
{
    public function __construct()
    {
        parent::__construct('No router assigned');
    }
}
