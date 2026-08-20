<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRealization;
use DomainException;
use Illuminate\Support\Facades\DB;

class CashAdvanceService
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function generateAdvanceNo(): string
    {
        $prefix = 'ADV';
        $year = now()->format('Y');

        $last = CashAdvance::where('advance_no', 'like', "$prefix-$year-%")
            ->orderByDesc('advance_no')
            ->value('advance_no');

        $seq = $last ? (int) explode('-', $last)[2] + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    public function create(array $data): CashAdvance
    {
        $data['advance_no'] = $this->generateAdvanceNo();
        $data['status'] = 'open';
        $data['realized_amount'] = 0;
        $data['returned_amount'] = 0;
        $data['user_id'] = auth()->id();

        $amount = (float) $data['amount'];
        $advanceAccount = $this->accountByCode('1150');
        $cashAccount = $this->accountByCode('1000');

        return DB::transaction(function () use ($data, $amount, $advanceAccount, $cashAccount) {
            $journal = $this->transactions->create([
                'type' => 'journal',
                'date' => $data['date'],
                'description' => 'Kas bon ' . $data['advance_no'],
                'lines' => [
                    ['account_id' => $advanceAccount, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            $data['transaction_id'] = $journal->id;

            return CashAdvance::create($data);
        });
    }

    public function realize(CashAdvance $advance, array $data): CashAdvanceRealization
    {
        $expenseLines = $data['lines'] ?? [];
        $returnedAmount = (float) ($data['returned_amount'] ?? 0);
        $expenseTotal = array_sum(array_map(fn ($l) => (float) $l['amount'], $expenseLines));
        $total = round($expenseTotal + $returnedAmount, 2);

        if ($total <= 0) {
            throw new DomainException('Minimal satu baris beban atau kas kembali harus diisi.');
        }

        $remaining = $advance->remaining;

        if ($returnedAmount > $remaining && $expenseTotal <= 0) {
            throw new DomainException('Kas kembali melebihi sisa kas bon.');
        }

        if ($total <= $remaining && $returnedAmount > $remaining) {
            throw new DomainException('Kas kembali melebihi sisa kas bon.');
        }

        return DB::transaction(function () use ($advance, $data, $expenseLines, $returnedAmount, $expenseTotal, $total, $remaining) {
            $lines = $this->buildRealizationLines($expenseLines, $returnedAmount, $total, $remaining);

            $journal = $this->transactions->create([
                'type' => 'journal',
                'date' => $data['date'],
                'description' => 'Realisasi kas bon ' . $advance->advance_no,
                'lines' => $lines,
            ]);

            $realization = $advance->realizations()->create([
                'date' => $data['date'],
                'transaction_id' => $journal->id,
                'description' => $data['description'] ?? null,
            ]);

            $advance->update([
                'realized_amount' => round((float) $advance->realized_amount + $expenseTotal, 2),
                'returned_amount' => round((float) $advance->returned_amount + $returnedAmount, 2),
            ]);

            $this->refreshStatus($advance);

            return $realization;
        });
    }

    public function delete(CashAdvance $advance): void
    {
        if ($advance->realizations()->exists()) {
            throw new DomainException('Kas bon sudah punya realisasi. Hapus tidak diizinkan.');
        }

        DB::transaction(function () use ($advance) {
            if ($advance->transaction_id) {
                $advance->transaction?->journalEntries()->delete();
                $advance->transaction?->delete();
            }

            $advance->delete();
        });
    }

    private function buildRealizationLines(array $expenseLines, float $returnedAmount, float $total, float $remaining): array
    {
        $lines = [];
        $advanceAccount = $this->accountByCode('1150');
        $cashAccount = $this->accountByCode('1000');

        foreach ($expenseLines as $line) {
            $amount = (float) $line['amount'];
            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'account_id' => $line['account_id'],
                'debit' => $amount,
                'credit' => 0,
                'description' => $line['description'] ?? null,
            ];
        }

        if ($returnedAmount > 0) {
            $lines[] = [
                'account_id' => $cashAccount,
                'debit' => $returnedAmount,
                'credit' => 0,
                'description' => 'Kas kembali',
            ];
        }

        if ($total <= $remaining) {
            $lines[] = [
                'account_id' => $advanceAccount,
                'debit' => 0,
                'credit' => $total,
            ];
        } else {
            $lines[] = [
                'account_id' => $advanceAccount,
                'debit' => 0,
                'credit' => $remaining,
            ];

            $excess = round($total - $remaining, 2);
            if ($excess > 0) {
                $lines[] = [
                    'account_id' => $cashAccount,
                    'debit' => 0,
                    'credit' => $excess,
                    'description' => 'Reimburse kurang bayar',
                ];
            }
        }

        return $lines;
    }

    private function refreshStatus(CashAdvance $advance): void
    {
        $advance->refresh();
        $cleared = (float) $advance->realized_amount + (float) $advance->returned_amount;
        $amount = (float) $advance->amount;

        $status = 'open';
        if ($cleared >= $amount) {
            $status = 'settled';
        } elseif ($cleared > 0) {
            $status = 'partial';
        }

        $advance->update(['status' => $status]);
    }

    private function accountByCode(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
