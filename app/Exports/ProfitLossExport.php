<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProfitLossExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $data,
        private readonly string $start,
        private readonly string $end,
    ) {}

    public function headings(): array
    {
        return ['Laporan Laba Rugi', "Periode: {$this->start} s/d {$this->end}"];
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['', ''];
        $rows[] = ['PENDAPATAN', 'Nominal'];
        foreach ($this->data['revenue'] as $r) {
            $rows[] = [$r['code'] . ' ' . $r['name'], $r['net']];
        }
        $rows[] = ['Total Pendapatan', $this->data['total_revenue']];

        $rows[] = ['', ''];
        $rows[] = ['BEBAN', 'Nominal'];
        foreach ($this->data['expense'] as $e) {
            $rows[] = [$e['code'] . ' ' . $e['name'], $e['net']];
        }
        $rows[] = ['Total Beban', $this->data['total_expense']];

        $rows[] = ['', ''];
        $rows[] = ['LABA (RUGI) BERSIH', $this->data['profit']];

        return $rows;
    }
}
