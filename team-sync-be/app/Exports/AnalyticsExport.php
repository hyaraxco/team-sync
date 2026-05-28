<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnalyticsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle, WithStyles, WithColumnFormatting
{
    /** @var string[] Column keys that should be formatted as IDR currency */
    private const CURRENCY_COLUMNS = [
        'Salary', 'Tax', 'BPJS', 'Deductions', 'Total Salary', 'Total Deductions',
        'Avg Salary', 'Total Cost', 'PPh21', 'BPJS TK', 'BPJS Kesehatan',
        'Average Salary', 'Total Deductions',
    ];

    /** @var string[] Column keys that should be formatted as percentages */
    private const PERCENTAGE_COLUMNS = [
        'Rate (%)', 'Attendance Rate (%)', 'Task Completion (%)', 'Approval Rate (%)',
        'Employee Growth (%)', 'Completion Rate (%)', 'Leave Utilization (%)',
        'Percentage (%)', 'Average Progress (%)',
    ];

    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
        private readonly string $title = 'Analytics'
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings));
        $headerRange = 'A1:'.$lastColumn.'1';

        return [
            // Header row — brand blue background, white bold text
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => Color::COLOR_WHITE],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF0C51D9'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF0A4AC4'],
                    ],
                ],
            ],
            // Data rows — alternating white/light-gray with borders
            'A2:'.$lastColumn.'1000' => [
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->headings as $index => $heading) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);

            if (in_array($heading, self::CURRENCY_COLUMNS, true)) {
                $formats[$col] = '#,##0';
            } elseif (in_array($heading, self::PERCENTAGE_COLUMNS, true)) {
                $formats[$col] = '0.0"%"';
            }
        }

        return $formats;
    }
}
