<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class BankStatementParser
{
  /**
   * @return array{
   *   opening_balance: float,
   *   closing_balance: float,
   *   period_start: ?string,
   *   period_end: ?string,
   *   account_no: ?string,
   *   lines: list<array{
   *     transaction_date: string,
   *     description: string,
   *     reference: ?string,
   *     debit: float,
   *     credit: float,
   *     balance: ?float
   *   }>
   * }
   */
    public function parse(string $path): array
    {
        $text = $this->extractText($path);

        if (trim($text) === '') {
            throw new DomainException('File PDF tidak dapat dibaca atau kosong.');
        }

        $opening = $this->extractSummaryAmount($text, 'Opening Balance');
        $closing = $this->extractSummaryAmount($text, 'Closing Balance');

        if ($opening === null || $closing === null) {
            throw new DomainException('Saldo awal/akhir tidak ditemukan dalam PDF rekening koran.');
        }

        $period = $this->extractPeriod($text);
        $accountNo = $this->extractAccountNo($text);
        $lines = $this->extractLines($text);

        if ($lines === []) {
            throw new DomainException('Tidak ada baris transaksi ditemukan dalam PDF rekening koran.');
        }

        return [
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'period_start' => $period['start'] ?? null,
            'period_end' => $period['end'] ?? null,
            'account_no' => $accountNo,
            'lines' => $lines,
        ];
    }

    private function extractText(string $path): string
    {
        $pythonText = $this->extractTextViaPython($path);
        if ($pythonText !== null && trim($pythonText) !== '') {
            return $pythonText;
        }

        try {
            $parser = new Parser();

            return $parser->parseFile($path)->getText();
        } catch (\Throwable) {
            throw new DomainException('Tidak dapat membaca file PDF rekening koran Mandiri Kopra.');
        }
    }

    private function extractTextViaPython(string $path): ?string
    {
        $script = base_path('scripts/extract_pdf_text.py');
        if (! is_file($script)) {
            return null;
        }

        $process = new Process(['python3', $script, $path]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }

    private function extractSummaryAmount(string $text, string $label): ?float
    {
        if (preg_match('/'.preg_quote($label, '/').'[^\d]*([\d,]+\.\d{2})/s', $text, $m)) {
            return $this->parseAmount($m[1]);
        }

        return null;
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    private function extractPeriod(string $text): array
    {
        if (preg_match('/(\d{1,2}\s+\w{3}\s+\d{4})\s*-\s*(\d{1,2}\s+\w{3}\s+\d{4})/', $text, $m)) {
            return [
                'start' => $this->parseDate($m[1]),
                'end' => $this->parseDate($m[2]),
            ];
        }

        return ['start' => null, 'end' => null];
    }

    private function extractAccountNo(string $text): ?string
    {
        if (preg_match('/\b(\d{10,16})\b/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return list<array{transaction_date: string, description: string, reference: ?string, debit: float, credit: float, balance: ?float}>
     */
    private function extractLines(string $text): array
    {
        $headerPos = stripos($text, 'Posting Date');
        $body = $headerPos !== false ? substr($text, $headerPos) : $text;

        $pattern = '/(?:-\s*)?([\d,]+\.\d{2})\s+(?:-\s*)?([\d,]+\.\d{2})\s+([\d,]+\.\d{2})/';
        preg_match_all($pattern, $body, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return [];
        }

        $lines = [];
        $prevEnd = 0;

        foreach ($matches[0] as $i => $fullMatch) {
            $matchStart = $fullMatch[1];
            $chunk = substr($body, $prevEnd, $matchStart - $prevEnd);
            $prevEnd = $matchStart + strlen($fullMatch[0]);

            $debit = $this->parseAmount($matches[1][$i][0]);
            $credit = $this->parseAmount($matches[2][$i][0]);
            $balance = $this->parseAmount($matches[3][$i][0]);

            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }

            // Skip summary/header triples (opening/closing totals in statement header).
            if ($balance > 10000000 && $debit > 10000000) {
                continue;
            }

            $dateStr = null;
            if (preg_match('/(\d{1,2}\s+\w{3}\s+\d{4})/', $chunk, $dateMatch)) {
                $dateStr = $this->parseDate($dateMatch[1]);
            }

            if ($dateStr === null) {
                continue;
            }

            $reference = null;
            if (preg_match('/\b(\d{5,12})\b/', $chunk, $refMatch)) {
                $candidate = $refMatch[1];
                if (! preg_match('/^\d{1,2}\s+\w{3}\s+\d{4}$/', $candidate)) {
                    $reference = $candidate;
                }
            }

            $description = $this->buildDescription($chunk);

            $lines[] = [
                'transaction_date' => $dateStr,
                'description' => $description,
                'reference' => $reference,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        }

        return $lines;
    }

    private function buildDescription(string $chunk): string
    {
        $chunk = preg_replace('/\d{1,2}\s+\w{3}\s+\d{4}/', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/\d{1,2}:\d{2}:\d{2}/', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/Posting Date|Remark|Reference No\.|Debit|Credit|Balance/i', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/\b\d{5,12}\b/', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/\s+/', ' ', $chunk) ?? $chunk;

        $description = trim($chunk, " -\n\r\t,");

        return $description !== '' ? $description : 'Transaksi';
    }

    private function parseAmount(string $value): float
    {
        $value = trim($value);
        $value = str_replace(',', '', $value);

        return round((float) $value, 2);
    }

    private function parseDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
