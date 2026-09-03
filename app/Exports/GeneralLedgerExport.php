<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GeneralLedgerExport implements FromArray, WithEvents, WithColumnWidths
{
    use RegistersEventListeners;

    private const TEAL = '0F766E';
    private const DARK = '1F2937';
    private const GRAY = '6B7280';
    private const BORDER = 'D0D0D0';
    private const LIGHT_FILL = 'F0FDFA';

    public function __construct(
        private readonly array $data,
        private readonly string $start,
        private readonly string $end,
        private readonly array $account = ['code' => '', 'name' => ''],
    ) {}

    public function columnWidths(): array
    {
        return ['A' => 13, 'B' => 17, 'C' => 48, 'D' => 16, 'E' => 16, 'F' => 18];
    }

    public function array(): array
    {
        $startFmt = Carbon::parse($this->start)->format('d-M-Y');
        $endFmt = Carbon::parse($this->end)->format('d-M-Y');

        $rows = [
            ['Prasasta Learning Centre', null, null, null, null, null],
            ['BUKU BESAR', null, null, null, null, null],
            [
                "Akun: {$this->account['code']} - {$this->account['name']}   |   Periode: {$startFmt} s/d {$endFmt}",
                null, null, null, null, null,
            ],
            [null, null, null, null, null, null],
            ['Tanggal', 'No. Jurnal', 'Keterangan', 'Debit', 'Kredit', 'Saldo'],
        ];

        // Row 6: Saldo Awal
        $rows[] = [null, null, 'Saldo Awal', null, null, (float) $this->data['opening_balance']];

        foreach ($this->data['entries'] as $entry) {
            $rows[] = [
                ExcelDate::PHPToExcel(Carbon::parse($entry['date'])),
                $entry['journal_no'],
                $entry['description'],
                (float) $entry['debit'],
                (float) $entry['credit'],
                (float) $entry['balance'],
            ];
        }

        $rows[] = [null, null, 'SALDO AKHIR', null, null, (float) $this->data['ending_balance']];

        return $rows;
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastRow = $sheet->getHighestRow();

        // --- Title block ---
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::DARK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::TEAL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::GRAY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(10);
        $sheet->getRowDimension(5)->setRowHeight(24);

        // --- Header row 5 ---
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEAL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // --- Borders over the whole table ---
        $sheet->getStyle("A5:F{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER],
                ],
            ],
        ]);

        // --- Number formats ---
        $sheet->getStyle("D6:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A7:A{$lastRow}")->getNumberFormat()->setFormatCode('dd-mmm-yyyy');

        // --- Alignments ---
        $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C6:C{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
        $sheet->getStyle("D6:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // --- Saldo Awal row (italic) ---
        $sheet->getStyle('A6:F6')->getFont()->setItalic(true);

        // --- SALDO AKHIR row (bold, light fill, medium teal top border) ---
        $sheet->getStyle("A{$lastRow}:F{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT_FILL]],
        ]);
        $topBorder = $sheet->getStyle("A{$lastRow}:F{$lastRow}")->getBorders()->getTop();
        $topBorder->setBorderStyle(Border::BORDER_MEDIUM);
        $topBorder->getColor()->setRGB(self::TEAL);

        // --- Freeze header + title block ---
        $sheet->freezePane('A6');
    }
}
