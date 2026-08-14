<?php

namespace App\Services;

use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;

class BankStatementImporter
{
    /**
     * Parse a bank statement CSV into structured lines.
     *
     * @return array<int, array{date:string, description:string, amount:float, source_ref:string}>
     */
    public function parse(UploadedFile $file): array
    {
        $content = $file->get();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // strip BOM

        $delimiter = $this->detectDelimiter($content);
        $rows = $this->csvToArray($content, $delimiter);

        $headerIndex = $this->detectHeaderIndex($rows);
        if ($headerIndex === null) {
            throw new DomainException('Header CSV tidak dikenali. Pastikan ada kolom tanggal, keterangan, dan nominal.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[$headerIndex]);
        $mapping = $this->detectColumns($header);

        if ($mapping['date'] === null || ($mapping['amount'] === null && ($mapping['debit'] === null || $mapping['credit'] === null))) {
            throw new DomainException('Kolom tanggal/nominal tidak ditemukan. Cek format CSV bank kamu.');
        }

        $lines = [];
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row, fn ($c) => trim((string) $c) !== ''))) {
                continue;
            }

            $line = $this->mapRow($row, $mapping);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        if (empty($lines)) {
            throw new DomainException('Tidak ada baris data yang berhasil dibaca.');
        }

        return $lines;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\n");
        $firstLine = $firstLine === false ? $content : $firstLine;

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function csvToArray(string $content, string $delimiter): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        return $rows;
    }

    private function detectHeaderIndex(array $rows): ?int
    {
        foreach ($rows as $i => $row) {
            $joined = strtolower(implode(' ', $row));
            if (str_contains($joined, 'tanggal') || str_contains($joined, 'date') || str_contains($joined, 'tgl')) {
                return $i;
            }
        }

        return null;
    }

    private function detectColumns(array $header): array
    {
        $map = ['date' => null, 'description' => null, 'debit' => null, 'credit' => null, 'amount' => null];

        foreach ($header as $i => $col) {
            $c = strtolower(trim((string) $col));

            if ($map['date'] === null && preg_match('/tanggal|date|tgl|posting/', $c)) {
                $map['date'] = $i;
            } elseif ($map['description'] === null && preg_match('/keterangan|uraian|deskripsi|description|remark|note|memo/', $c)) {
                $map['description'] = $i;
            } elseif ($map['debit'] === null && preg_match('/^debit$|^debet$/', $c)) {
                $map['debit'] = $i;
            } elseif ($map['credit'] === null && preg_match('/^credit$|^kredit$/', $c)) {
                $map['credit'] = $i;
            } elseif ($map['amount'] === null && preg_match('/amount|nominal|jumlah|mutasi|nilai|value|saldo|balance/', $c)) {
                $map['amount'] = $i;
            }
        }

        return $map;
    }

    private function mapRow(array $row, array $mapping): ?array
    {
        $get = fn ($key) => $mapping[$key] !== null ? ($row[$mapping[$key]] ?? null) : null;

        $date = $this->parseDate($get('date'));
        if ($date === null) {
            return null;
        }

        $description = trim((string) ($get('description') ?? ''));

        $amount = null;
        if ($mapping['debit'] !== null && $mapping['credit'] !== null) {
            $debit = $this->parseAmount($get('debit'));
            $credit = $this->parseAmount($get('credit'));
            if ($debit !== null && $credit !== null) {
                $amount = round($credit - $debit, 2);
            }
        }

        if ($amount === null) {
            $amount = $this->parseAmount($get('amount'));
        }

        if ($amount === null) {
            return null;
        }

        return [
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'source_ref' => md5($date . '|' . $description . '|' . $amount . '|' . implode('|', $row)),
        ];
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // dd/mm/yyyy or dd-mm-yyyy (Indonesian convention)
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $raw = trim($value);
        $negative = str_contains($raw, '-') || (str_contains($raw, '(') && str_contains($raw, ')'));

        $clean = preg_replace('/[^0-9,.-]/', '', $raw);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean); // dot = thousands
            $clean = str_replace(',', '.', $clean); // comma = decimal
        } elseif (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean); // multiple dots = thousands
        }

        $num = (float) $clean;

        return $negative ? -abs($num) : $num;
    }
}
