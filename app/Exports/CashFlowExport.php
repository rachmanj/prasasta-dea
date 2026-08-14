<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CashFlowExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $data,
        private readonly string $start,
        private readonly string $end,
    ) {}

    public function headings(): array
    {
        return ['Laporan Arus Kas', "Periode: {$this->start} s/d {$this->end}"];
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['', ''];
        $rows[] = ['UANG MASUK', 'Nominal'];
        foreach ($this->data['inflow_by_category'] as $k => $v) {
            $rows[] = [$k, $v];
        }
        $rows[] = ['Total Uang Masuk', $this->data['total_inflow']];

        $rows[] = ['', ''];
        $rows[] = ['UANG KELUAR', 'Nominal'];
        foreach ($this->data['outflow_by_category'] as $k => $v) {
            $rows[] = [$k, $v];
        }
        $rows[] = ['Total Uang Keluar', $this->data['total_outflow']];

        $rows[] = ['', ''];
        $rows[] = ['ARUS KAS BERSIH', $this->data['net']];

        return $rows;
    }
}
