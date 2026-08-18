<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class GeneralLedgerExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $data,
        private readonly string $start,
        private readonly string $end,
    ) {}

    public function headings(): array
    {
        return ['Tanggal', 'No. Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data['entries'] as $entry) {
            $rows[] = [
                $entry['date'],
                $entry['journal_no'],
                $entry['description'],
                $entry['debit'],
                $entry['credit'],
                $entry['balance'],
            ];
        }

        return $rows;
    }
}
