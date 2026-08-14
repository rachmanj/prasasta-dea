<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionsExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['No. Jurnal', 'Tanggal', 'Tipe', 'Keterangan', 'Akun', 'Debit', 'Kredit'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
