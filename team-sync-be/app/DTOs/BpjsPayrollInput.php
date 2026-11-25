<?php

namespace App\DTOs;

use App\Exceptions\PayrollStateException;

final readonly class BpjsPayrollInput
{
    public function __construct(
        public int $basicWage,
        public int $fixedAllowances,
        public int $jkkRiskClass,
        public int $healthFloor,
    ) {
        if ($basicWage <= 0 || $fixedAllowances < 0 || $jkkRiskClass < 1 || $jkkRiskClass > 5 || $healthFloor <= 0) {
            throw new PayrollStateException('Invalid BPJS basic wage, fixed allowances, JKK class or health floor.');
        }
    }
}
