<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReceivablesPayablesExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $data) {}

    public function headings(): array
    {
        return ['Posisi Hutang / Piutang'];
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['', ''];
        $rows[] = ['PIUTANG', ''];
        $rows[] = ['No. Tagihan', 'Kontak', 'Jatuh Tempo', 'Sisa', 'Status'];
        foreach ($this->data['receivables'] as $b) {
            $rows[] = [$b->bill_no, $b->contact->name, $b->due_date->format('Y-m-d'), $b->remaining, $b->status];
        }
        $rows[] = ['Total Piutang', '', '', $this->data['total_receivable'], ''];

        $rows[] = ['', ''];
        $rows[] = ['HUTANG', ''];
        $rows[] = ['No. Tagihan', 'Kontak', 'Jatuh Tempo', 'Sisa', 'Status'];
        foreach ($this->data['payables'] as $b) {
            $rows[] = [$b->bill_no, $b->contact->name, $b->due_date->format('Y-m-d'), $b->remaining, $b->status];
        }
        $rows[] = ['Total Hutang', '', '', $this->data['total_payable'], ''];

        return $rows;
    }
}
