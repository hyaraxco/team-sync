<?php

namespace App\Exceptions;

use RuntimeException;

class PayrollAlreadyPaidException extends RuntimeException
{
    public function __construct(string $message = 'Payroll has already been paid and cannot be modified.')
    {
        parent::__construct($message);
    }
}
