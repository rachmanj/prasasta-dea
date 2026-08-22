<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgramProfitLossExport implements FromArray, WithHeadings
{
    private const TYPE_LABELS = [
        'group' => 'Kelompok',
        'individual' => 'Individu',
    ];

    private const STATUS_LABELS = [
        'active' => 'Aktif',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public function __construct(private readonly array $data) {}

    public function headings(): array
    {
        return ['Kode', 'Nama Program', 'Jenis', 'Periode', 'Status', 'Pendapatan', 'Biaya', 'Laba'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data['programs'] as $p) {
            $start = $p['start_date'] ?? '-';
            $end = $p['end_date'] ?? '-';
            $periode = "{$start} s/d {$end}";

            $rows[] = [
                $p['code'],
                $p['name'],
                self::TYPE_LABELS[$p['type']] ?? $p['type'],
                $periode,
                self::STATUS_LABELS[$p['status']] ?? $p['status'],
                (float) $p['revenue'],
                (float) $p['expense'],
                (float) $p['profit'],
            ];
        }

        $rows[] = ['', '', '', '', 'TOTAL', $this->data['total_revenue'], $this->data['total_expense'], $this->data['total_profit']];

        return $rows;
    }
}
