<?php

namespace App\Exceptions;

use RuntimeException;

class PayrollReconciliationBlockedException extends RuntimeException
{
    private array $details;

    public function __construct(string $message = 'Payroll payment is blocked by unresolved reconciliation issues.', array $details = [])
    {
        $this->details = $details;
        parent::__construct($message);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
