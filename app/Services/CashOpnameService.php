<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashOpname;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashOpnameService
{
    public const DENOMINATIONS = [
        ['denomination' => 100000, 'type' => 'banknote'],
        ['denomination' => 50000, 'type' => 'banknote'],
        ['denomination' => 20000, 'type' => 'banknote'],
        ['denomination' => 10000, 'type' => 'banknote'],
        ['denomination' => 5000, 'type' => 'banknote'],
        ['denomination' => 2000, 'type' => 'banknote'],
        ['denomination' => 1000, 'type' => 'banknote'],
        ['denomination' => 500, 'type' => 'banknote'],
        ['denomination' => 100, 'type' => 'banknote'],
        ['denomination' => 1000, 'type' => 'coin'],
        ['denomination' => 500, 'type' => 'coin'],
        ['denomination' => 200, 'type' => 'coin'],
        ['denomination' => 100, 'type' => 'coin'],
        ['denomination' => 50, 'type' => 'coin'],
        ['denomination' => 25, 'type' => 'coin'],
    ];

    public function __construct(
        private readonly ReportService $reports,
        private readonly TransactionService $transactions,
    ) {}

    public function generateNumber(?string $date = null): string
    {
        $year = \Carbon\Carbon::parse($date ?? now())->format('Y');
        $prefix = 'OPN';

        $last = CashOpname::where('number', 'like', "$prefix-$year-%")
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? (int) explode('-', $last)[2] + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    public function create(array $data): CashOpname
    {
        $cashAccount = Account::where('code', '1000')->firstOrFail();
        $date = $data['date'];
        $bookBalance = $this->reports->accountBalance($cashAccount->id, $date);

        $lines = $this->buildLines($data['lines'] ?? []);
        $physicalBalance = round(array_sum(array_column($lines, 'amount')), 2);
        $difference = round($bookBalance - $physicalBalance, 2);

        return DB::transaction(function () use ($data, $cashAccount, $date, $bookBalance, $physicalBalance, $difference, $lines) {
            $opname = CashOpname::create([
                'number' => $this->generateNumber($date),
                'date' => $date,
                'account_id' => $cashAccount->id,
                'book_balance' => $bookBalance,
                'physical_balance' => $physicalBalance,
                'difference' => $difference,
                'status' => 'open',
                'prepared_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $opname->lines()->create($line);
            }

            return $opname->load('lines');
        });
    }

    public function adjust(CashOpname $opname): CashOpname
    {
        if ((float) $opname->difference == 0) {
            throw new DomainException('Tidak ada selisih untuk disesuaikan.');
        }

        if ($opname->status !== 'open') {
            throw new DomainException('Opname sudah disesuaikan sebelumnya.');
        }

        if ($opname->adjustment_transaction_id) {
            throw new DomainException('Opname sudah disesuaikan sebelumnya.');
        }

        $amount = abs((float) $opname->difference);
        $cashAccount = $this->accountByCode('1000');
        $diff = (float) $opname->difference;

        $journalLines = $diff > 0
            ? [
                ['account_id' => $this->accountByCode('5700'), 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['account_id' => $cashAccount, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->accountByCode('4600'), 'debit' => 0, 'credit' => $amount],
            ];

        return DB::transaction(function () use ($opname, $journalLines) {
            $transaction = $this->transactions->create([
                'type' => 'journal',
                'date' => $opname->date->toDateString(),
                'description' => 'Penyesuaian selisih kas opname '.$opname->number,
                'lines' => $journalLines,
            ]);

            $opname->update([
                'status' => 'adjusted',
                'adjustment_transaction_id' => $transaction->id,
            ]);

            return $opname->fresh(['lines', 'adjustmentTransaction.journalEntries.account', 'preparedBy', 'account']);
        });
    }

    /** @return array<int, array{denomination: int, type: string, units: int, amount: float}> */
    private function buildLines(array $inputLines): array
    {
        $unitsByKey = [];
        foreach ($inputLines as $line) {
            $key = ($line['type'] ?? '').'-'.($line['denomination'] ?? 0);
            $unitsByKey[$key] = (int) ($line['units'] ?? 0);
        }

        $result = [];
        foreach (self::DENOMINATIONS as $denom) {
            $key = $denom['type'].'-'.$denom['denomination'];
            $units = max(0, $unitsByKey[$key] ?? 0);

            $result[] = [
                'denomination' => $denom['denomination'],
                'type' => $denom['type'],
                'units' => $units,
                'amount' => round($denom['denomination'] * $units, 2),
            ];
        }

        return $result;
    }

    private function accountByCode(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
