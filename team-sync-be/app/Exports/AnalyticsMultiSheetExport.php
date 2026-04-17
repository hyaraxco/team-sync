<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AnalyticsMultiSheetExport implements WithMultipleSheets
{
    /**
     * @param  AnalyticsExport[]  $sheets
     */
    public function __construct(
        private readonly array $sheets
    ) {}

    public function sheets(): array
    {
        return $this->sheets;
    }
}
