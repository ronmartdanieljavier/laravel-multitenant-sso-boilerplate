<?php

namespace App\Reports\Exports;

use App\Models\Central\Report;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Report $report,
        private readonly array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        return array_map(
            fn (string $key) => Str::headline($key),
            array_keys($this->rows[0]),
        );
    }

    public function title(): string
    {
        return Str::headline($this->report->type);
    }
}
