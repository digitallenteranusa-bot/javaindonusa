<?php

namespace App\Exceptions;

use Exception;

class CannotDeleteWithDependentsException extends Exception
{
    protected string $entity;
    protected string $dependent;

    public function __construct(string $entity, string $dependent)
    {
        $this->entity = $entity;
        $this->dependent = $dependent;
        parent::__construct("Tidak dapat menghapus {$entity} yang masih memiliki {$dependent}");
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getDependent(): string
    {
        return $this->dependent;
    }
}
