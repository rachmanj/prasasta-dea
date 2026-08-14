<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransactionService
{
    private const PREFIX = [
        'receipt' => 'BKM',
        'payment' => 'BKK',
        'transfer' => 'TRF',
        'journal' => 'JU',
    ];

    public function generateJournalNo(string $type, string|CarbonInterface $date): string
    {
        $prefix = self::PREFIX[$type] ?? 'JU';
        $year = \Carbon\Carbon::parse($date)->format('Y');

        $last = Transaction::where('journal_no', 'like', "$prefix-$year-%")
            ->orderByDesc('journal_no')
            ->value('journal_no');

        $seq = 1;
        if ($last) {
            $seq = (int) explode('-', $last)[2] + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    public function create(array $data): Transaction
    {
        $data['journal_no'] = $this->generateJournalNo($data['type'], $data['date']);
        $data['status'] = 'posted';
        $data['user_id'] = auth()->id();

        return DB::transaction(function () use ($data) {
            $transaction = Transaction::create($data);

            foreach ($this->buildLines($data) as $line) {
                $transaction->journalEntries()->create($line);
            }

            return $transaction;
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $transaction->journalEntries()->delete();
            $transaction->update($data);

            foreach ($this->buildLines($data) as $line) {
                $transaction->journalEntries()->create($line);
            }

            return $transaction->fresh();
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->journalEntries()->delete();
            $transaction->delete();
        });
    }

    private function buildLines(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        $description = $data['description'] ?? null;

        return match ($data['type']) {
            'receipt' => [
                ['account_id' => $data['account_id'], 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $data['category_id'], 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
            'payment' => [
                ['account_id' => $data['category_id'], 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $data['account_id'], 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
            'transfer' => [
                ['account_id' => $data['to_account_id'], 'debit' => $amount, 'credit' => 0, 'description' => $description],
                ['account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $amount, 'description' => $description],
            ],
            'journal' => $data['lines'],
            default => throw new InvalidArgumentException("Unknown transaction type: {$data['type']}"),
        };
    }
}
