<?php

namespace App\Exceptions;

class AslBelgisiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0)
    {
        parent::__construct("[ASL BELGISI {$httpStatus}] {$message}");
    }
}
