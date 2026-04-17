<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $columns,
        private readonly array $headings,
        private readonly string $title = 'Payroll Report'
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows->map(function (array $row) {
            return collect($this->columns)->map(fn (string $column) => $row[$column] ?? null)->all();
        });
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }
}
