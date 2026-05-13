<?php

namespace App\Exceptions;

use RuntimeException;

class PayrollStateException extends RuntimeException
{
    public function __construct(string $message = 'Payroll is not in the required state for this operation.')
    {
        parent::__construct($message);
    }
}
