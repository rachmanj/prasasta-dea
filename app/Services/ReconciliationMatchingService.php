<?php

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\MatchGroupBankLine;
use App\Models\MatchGroupBookLine;
use App\Models\ReconciliationMatchGroup;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReconciliationMatchingService
{
    public function __construct(
        private ReconciliationBalanceService $balanceService,
    ) {}

    public function clearAutoGroups(BankReconciliation $reconciliation): void
    {
        $groups = ReconciliationMatchGroup::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('match_type', ReconciliationMatchGroup::TYPE_AUTO)
            ->get();

        foreach ($groups as $group) {
            $this->unmatchGroup($reconciliation, $group);
        }
    }

    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $this->assertEditable($reconciliation);
        $this->clearAutoGroups($reconciliation);

        $matched = 0;
        $matched += $this->matchExact($reconciliation, 1);
        $matched += $this->matchSplitManyBookToOneBank($reconciliation, 5, 7);
        $matched += $this->matchSplitManyBankToOneBook($reconciliation, 5, 7);

        return $matched;
    }

    /**
     * @param  list<int>  $bankLineIds
     * @param  list<int>  $transactionIds
     */
    public function manualMatch(
        BankReconciliation $reconciliation,
        array $bankLineIds,
        array $transactionIds,
    ): ReconciliationMatchGroup {
        $this->assertEditable($reconciliation);

        if ($bankLineIds === [] || $transactionIds === []) {
            throw new \DomainException('Pilih minimal satu baris bank dan satu baris buku.');
        }

        return DB::transaction(function () use ($reconciliation, $bankLineIds, $transactionIds) {
            $bankLines = BankStatementLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->whereIn('id', $bankLineIds)
                ->lockForUpdate()
                ->get();

            $transactions = $this->balanceService->bookTransactions($reconciliation)
                ->whereIn('id', $transactionIds)
                ->values();

            if ($bankLines->count() !== count($bankLineIds) || $transactions->count() !== count($transactionIds)) {
                throw new \DomainException('Salah satu baris yang dipilih tidak valid.');
            }

            foreach ($bankLines as $line) {
                if (! $line->isAvailableForMatching()) {
                    throw new \DomainException('Baris bank #'.$line->id.' tidak dapat dicocokkan.');
                }
            }

            $matchedIds = $this->balanceService->matchedTransactionIds($reconciliation);
            foreach ($transactions as $transaction) {
                if (in_array($transaction->id, $matchedIds, true)) {
                    throw new \DomainException('Transaksi #'.$transaction->id.' sudah dicocokkan.');
                }
            }

            $bankTotal = round($bankLines->sum(fn (BankStatementLine $line) => $line->netAmount()), 2);
            $bookTotal = round($transactions->sum(fn (Transaction $tx) => $this->transactionNet($tx, $reconciliation->account_id)), 2);

            if (! $this->balanceService->totalsAreBalanced($bankTotal, $bookTotal)) {
                throw new \DomainException(
                    'Grup cocok tidak balance. Net bank '
                    .number_format($bankTotal, 2, ',', '.')
                    .' + net buku '
                    .number_format($bookTotal, 2, ',', '.')
                    .' harus nol.'
                );
            }

            return $this->createGroup(
                $reconciliation,
                $bankLines,
                $transactions,
                ReconciliationMatchGroup::TYPE_MANUAL,
                BankStatementLine::MATCH_MANUAL,
            );
        });
    }

    public function unmatchGroup(
        BankReconciliation $reconciliation,
        ReconciliationMatchGroup $group,
    ): void {
        $this->assertEditable($reconciliation);

        if ((int) $group->bank_reconciliation_id !== (int) $reconciliation->id) {
            throw new \DomainException('Grup cocok tidak termasuk sesi ini.');
        }

        DB::transaction(function () use ($group) {
            $bankLineIds = MatchGroupBankLine::query()
                ->where('match_group_id', $group->id)
                ->pluck('bank_statement_line_id')
                ->all();

            BankStatementLine::query()
                ->whereIn('id', $bankLineIds)
                ->update(['matched_status' => BankStatementLine::MATCH_UNMATCHED]);

            $group->delete();
        });
    }

    private function matchExact(BankReconciliation $reconciliation, int $dateToleranceDays): int
    {
        $matched = 0;
        $bankLines = $this->availableBankLines($reconciliation);
        $bookByKey = $this->bucketTransactionsByAmount($this->availableTransactions($reconciliation));

        foreach ($bankLines as $bankLine) {
            $candidates = $this->candidatesForBankLine($bankLine, $bookByKey);
            $best = null;
            $bestDays = PHP_INT_MAX;

            foreach ($candidates as $transaction) {
                if (! $this->amountsAreOpposite($bankLine, $transaction, $reconciliation->account_id)) {
                    continue;
                }

                $days = abs($bankLine->transaction_date->diffInDays($transaction->date));
                if ($days > $dateToleranceDays) {
                    continue;
                }

                if ($days < $bestDays) {
                    $bestDays = $days;
                    $best = $transaction;
                }
            }

            if (! $best) {
                continue;
            }

            $this->createGroup(
                $reconciliation,
                collect([$bankLine]),
                collect([$best]),
                ReconciliationMatchGroup::TYPE_AUTO,
                BankStatementLine::MATCH_MATCHED,
            );

            $this->removeFromBuckets($bookByKey, $best, $reconciliation->account_id);
            $matched++;
        }

        return $matched;
    }

    private function matchSplitManyBookToOneBank(BankReconciliation $reconciliation, int $maxLines, int $dateToleranceDays): int
    {
        $matched = 0;
        $transactions = $this->availableTransactions($reconciliation);

        foreach ($this->availableBankLines($reconciliation) as $bankLine) {
            $window = $transactions
                ->filter(fn (Transaction $tx) => abs($bankLine->transaction_date->diffInDays($tx->date)) <= $dateToleranceDays)
                ->take(20)
                ->values();

            $subset = $this->findSubsetSummingToTarget(
                $window,
                -1 * $bankLine->netAmount(),
                $maxLines,
                $reconciliation->account_id,
            );

            if ($subset === null) {
                continue;
            }

            $this->createGroup(
                $reconciliation,
                collect([$bankLine]),
                collect($subset),
                ReconciliationMatchGroup::TYPE_AUTO,
                BankStatementLine::MATCH_MATCHED,
            );

            $usedIds = collect($subset)->pluck('id')->all();
            $transactions = $transactions->reject(fn (Transaction $tx) => in_array($tx->id, $usedIds, true))->values();
            $matched++;
        }

        return $matched;
    }

    private function matchSplitManyBankToOneBook(BankReconciliation $reconciliation, int $maxLines, int $dateToleranceDays): int
    {
        $matched = 0;
        $bankLines = $this->availableBankLines($reconciliation);

        foreach ($this->availableTransactions($reconciliation) as $transaction) {
            $window = $bankLines
                ->filter(fn (BankStatementLine $line) => abs($line->transaction_date->diffInDays($transaction->date)) <= $dateToleranceDays)
                ->take(20)
                ->values();

            $target = -1 * $this->transactionNet($transaction, $reconciliation->account_id);
            $subset = $this->findSubsetSummingToTargetBank($window, $target, $maxLines);

            if ($subset === null) {
                continue;
            }

            $this->createGroup(
                $reconciliation,
                collect($subset),
                collect([$transaction]),
                ReconciliationMatchGroup::TYPE_AUTO,
                BankStatementLine::MATCH_MATCHED,
            );

            $usedIds = collect($subset)->pluck('id')->all();
            $bankLines = $bankLines->reject(fn (BankStatementLine $line) => in_array($line->id, $usedIds, true))->values();
            $matched++;
        }

        return $matched;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<Transaction>|null
     */
    private function findSubsetSummingToTarget(
        Collection $transactions,
        float $target,
        int $maxSize,
        int $accountId,
    ): ?array {
        $items = $transactions->values()->all();
        $count = count($items);

        for ($size = 2; $size <= min($maxSize, $count); $size++) {
            $result = $this->subsetSearchTransactions($items, $target, $size, 0, [], 0.0, $accountId);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  list<Transaction>  $items
     * @param  list<Transaction>  $current
     * @return list<Transaction>|null
     */
    private function subsetSearchTransactions(
        array $items,
        float $target,
        int $size,
        int $start,
        array $current,
        float $sum,
        int $accountId,
    ): ?array {
        if (count($current) === $size) {
            return abs($sum - $target) < ReconciliationBalanceService::TOLERANCE ? $current : null;
        }

        $remaining = $size - count($current);
        for ($i = $start; $i <= count($items) - $remaining; $i++) {
            $current[] = $items[$i];
            $found = $this->subsetSearchTransactions(
                $items,
                $target,
                $size,
                $i + 1,
                $current,
                $sum + $this->transactionNet($items[$i], $accountId),
                $accountId,
            );
            if ($found !== null) {
                return $found;
            }
            array_pop($current);
        }

        return null;
    }

    /**
     * @param  Collection<int, BankStatementLine>  $lines
     * @return list<BankStatementLine>|null
     */
    private function findSubsetSummingToTargetBank(Collection $lines, float $target, int $maxSize): ?array
    {
        $items = $lines->values()->all();
        $count = count($items);

        for ($size = 2; $size <= min($maxSize, $count); $size++) {
            $result = $this->subsetSearchBankLines($items, $target, $size, 0, [], 0.0);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param  list<BankStatementLine>  $items
     * @param  list<BankStatementLine>  $current
     * @return list<BankStatementLine>|null
     */
    private function subsetSearchBankLines(
        array $items,
        float $target,
        int $size,
        int $start,
        array $current,
        float $sum,
    ): ?array {
        if (count($current) === $size) {
            return abs($sum - $target) < ReconciliationBalanceService::TOLERANCE ? $current : null;
        }

        $remaining = $size - count($current);
        for ($i = $start; $i <= count($items) - $remaining; $i++) {
            $current[] = $items[$i];
            $found = $this->subsetSearchBankLines(
                $items,
                $target,
                $size,
                $i + 1,
                $current,
                $sum + $items[$i]->netAmount(),
            );
            if ($found !== null) {
                return $found;
            }
            array_pop($current);
        }

        return null;
    }

    private function amountsAreOpposite(BankStatementLine $bankLine, Transaction $transaction, int $accountId): bool
    {
        $entry = $transaction->journalEntries->firstWhere('account_id', $accountId);
        if (! $entry) {
            return false;
        }

        return abs(round((float) $bankLine->debit, 2) - round((float) $entry->credit, 2)) < ReconciliationBalanceService::TOLERANCE
            && abs(round((float) $bankLine->credit, 2) - round((float) $entry->debit, 2)) < ReconciliationBalanceService::TOLERANCE;
    }

  /**
   * @param  Collection<int, BankStatementLine>  $bankLines
   * @param  Collection<int, Transaction>  $transactions
   */
    private function createGroup(
        BankReconciliation $reconciliation,
        Collection $bankLines,
        Collection $transactions,
        string $matchType,
        string $bankMatchStatus,
    ): ReconciliationMatchGroup {
        $bankTotal = round($bankLines->sum(fn (BankStatementLine $line) => $line->netAmount()), 2);
        $bookTotal = round($transactions->sum(fn (Transaction $tx) => $this->transactionNet($tx, $reconciliation->account_id)), 2);

        return DB::transaction(function () use (
            $reconciliation,
            $bankLines,
            $transactions,
            $matchType,
            $bankMatchStatus,
            $bankTotal,
            $bookTotal,
        ) {
            $group = ReconciliationMatchGroup::create([
                'bank_reconciliation_id' => $reconciliation->id,
                'match_type' => $matchType,
                'bank_total' => $bankTotal,
                'book_total' => $bookTotal,
                'difference' => round($bankTotal + $bookTotal, 2),
                'created_by' => Auth::id(),
            ]);

            foreach ($bankLines as $bankLine) {
                MatchGroupBankLine::create([
                    'match_group_id' => $group->id,
                    'bank_statement_line_id' => $bankLine->id,
                ]);
                $bankLine->update(['matched_status' => $bankMatchStatus]);
            }

            foreach ($transactions as $transaction) {
                MatchGroupBookLine::create([
                    'match_group_id' => $group->id,
                    'transaction_id' => $transaction->id,
                ]);
            }

            return $group->fresh();
        });
    }

    /** @return Collection<int, BankStatementLine> */
    private function availableBankLines(BankReconciliation $reconciliation): Collection
    {
        return BankStatementLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('matched_status', BankStatementLine::MATCH_UNMATCHED)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Transaction> */
    private function availableTransactions(BankReconciliation $reconciliation): Collection
    {
        $matchedIds = $this->balanceService->matchedTransactionIds($reconciliation);

        return $this->balanceService->bookTransactions($reconciliation)
            ->reject(fn (Transaction $tx) => in_array($tx->id, $matchedIds, true))
            ->values();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, Collection<int, Transaction>>
     */
    private function bucketTransactionsByAmount(Collection $transactions): array
    {
        $buckets = [];
        foreach ($transactions as $transaction) {
            $entry = $transaction->journalEntries->first();
            if (! $entry) {
                continue;
            }
            $key = $this->amountBucketKey((float) $entry->debit, (float) $entry->credit);
            $buckets[$key] ??= collect();
            $buckets[$key]->push($transaction);
        }

        return $buckets;
    }

    /**
     * @param  array<string, Collection<int, Transaction>>  $buckets
     * @return Collection<int, Transaction>
     */
    private function candidatesForBankLine(BankStatementLine $bankLine, array $buckets): Collection
    {
        $key = $this->amountBucketKey((float) $bankLine->credit, (float) $bankLine->debit);

        return $buckets[$key] ?? collect();
    }

    /**
     * @param  array<string, Collection<int, Transaction>>  $buckets
     */
    private function removeFromBuckets(array &$buckets, Transaction $transaction, int $accountId): void
    {
        $entry = $transaction->journalEntries->firstWhere('account_id', $accountId);
        if (! $entry) {
            return;
        }

        $key = $this->amountBucketKey((float) $entry->debit, (float) $entry->credit);
        if (! isset($buckets[$key])) {
            return;
        }

        $buckets[$key] = $buckets[$key]->reject(fn (Transaction $tx) => $tx->id === $transaction->id)->values();
    }

    private function amountBucketKey(float $debit, float $credit): string
    {
        return number_format(round($debit, 2), 2, '.', '').'|'.number_format(round($credit, 2), 2, '.', '');
    }

    private function transactionNet(Transaction $transaction, int $accountId): float
    {
        $entry = $transaction->journalEntries->firstWhere('account_id', $accountId);
        if (! $entry) {
            return 0.0;
        }

        return round((float) $entry->debit - (float) $entry->credit, 2);
    }

    private function assertEditable(BankReconciliation $reconciliation): void
    {
        if (! $reconciliation->isEditable()) {
            throw new \DomainException('Sesi rekonsiliasi sudah selesai dan tidak dapat diubah.');
        }
    }
}
