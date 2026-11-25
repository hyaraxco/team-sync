<?php

namespace App\DTOs;

use Carbon\CarbonInterface;

class NikTaxpayerAcceptanceResultDto
{
    public function __construct(
        public readonly CarbonInterface $accepted_at,
    ) {}
}
