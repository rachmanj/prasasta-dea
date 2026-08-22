<?php

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\MatchGroupBookLine;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class ReconciliationBalanceService
{
    public const TOLERANCE = 0.005;

    public function bankNet(BankReconciliation $reconciliation): float
    {
        return (float) BankStatementLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('matched_status', '!=', BankStatementLine::MATCH_EXCLUDED)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as net')
            ->value('net');
    }

    public function bookNet(BankReconciliation $reconciliation): float
    {
        $total = 0.0;

        foreach ($this->bookLines($reconciliation) as $line) {
            $total += $line['net'];
        }

        return round($total, 2);
    }

    public function difference(BankReconciliation $reconciliation): float
    {
        return round($this->bankNet($reconciliation) + $this->bookNet($reconciliation), 2);
    }

    public function unmatchedBankNet(BankReconciliation $reconciliation): float
    {
        return round((float) BankStatementLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('matched_status', BankStatementLine::MATCH_UNMATCHED)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as net')
            ->value('net'), 2);
    }

    public function unmatchedBookNet(BankReconciliation $reconciliation): float
    {
        $total = 0.0;

        foreach ($this->unmatchedBookLines($reconciliation) as $line) {
            $total += $line['net'];
        }

        return round($total, 2);
    }

    public function adjustedBank(BankReconciliation $reconciliation): float
    {
        $closing = round((float) ($reconciliation->closing_balance_bank ?? 0), 2);

        return round($closing + $this->unmatchedBookNet($reconciliation), 2);
    }

    public function adjustedBook(BankReconciliation $reconciliation): float
    {
        $closing = round((float) ($reconciliation->closing_balance_book ?? 0), 2);

        return round($closing - $this->unmatchedBankNet($reconciliation), 2);
    }

    public function unexplained(BankReconciliation $reconciliation): float
    {
        return round($this->adjustedBank($reconciliation) - $this->adjustedBook($reconciliation), 2);
    }

    public function unmatchedBankCount(BankReconciliation $reconciliation): int
    {
        return BankStatementLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('matched_status', BankStatementLine::MATCH_UNMATCHED)
            ->count();
    }

    public function unmatchedBookCount(BankReconciliation $reconciliation): int
    {
        return count($this->unmatchedBookLines($reconciliation));
    }

    public function isBalanced(BankReconciliation $reconciliation): bool
    {
        if ($this->unmatchedBankCount($reconciliation) > 0 || $this->unmatchedBookCount($reconciliation) > 0) {
            return false;
        }

        if (abs($this->difference($reconciliation)) >= self::TOLERANCE) {
            return false;
        }

        if (abs($this->unexplained($reconciliation)) >= self::TOLERANCE) {
            return false;
        }

        return true;
    }

    public function totalsAreBalanced(float $bankTotal, float $bookTotal): bool
    {
        return abs($bankTotal + $bookTotal) < self::TOLERANCE;
    }

    /**
     * @return list<array{
     *   transaction: Transaction,
     *   debit: float,
     *   credit: float,
     *   net: float,
     *   matched_status: string
     * }>
     */
    public function bookLines(BankReconciliation $reconciliation): array
    {
        $matchedIds = $this->matchedTransactionIds($reconciliation);
        $lines = [];

        foreach ($this->bookTransactions($reconciliation) as $transaction) {
            $entry = $this->bankJournalEntry($transaction, $reconciliation->account_id);
            if ($entry === null) {
                continue;
            }

            $debit = round((float) $entry->debit, 2);
            $credit = round((float) $entry->credit, 2);
            $matchedStatus = in_array($transaction->id, $matchedIds, true)
                ? 'matched'
                : 'unmatched';

            $lines[] = [
                'transaction' => $transaction,
                'debit' => $debit,
                'credit' => $credit,
                'net' => round($debit - $credit, 2),
                'matched_status' => $matchedStatus,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{transaction: Transaction, debit: float, credit: float, net: float, matched_status: string}>
     */
    public function unmatchedBookLines(BankReconciliation $reconciliation): array
    {
        return array_values(array_filter(
            $this->bookLines($reconciliation),
            fn (array $line) => $line['matched_status'] === 'unmatched',
        ));
    }

    /** @return Collection<int, Transaction> */
    public function bookTransactions(BankReconciliation $reconciliation): Collection
    {
        $start = $reconciliation->period->copy()->startOfMonth()->toDateString();
        $end = $reconciliation->period->copy()->endOfMonth()->toDateString();

        return Transaction::query()
            ->whereBetween('date', [$start, $end])
            ->whereHas('journalEntries', fn ($q) => $q->where('account_id', $reconciliation->account_id))
            ->with(['journalEntries' => fn ($q) => $q->where('account_id', $reconciliation->account_id)])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /** @return list<int> */
    public function matchedTransactionIds(BankReconciliation $reconciliation): array
    {
        return MatchGroupBookLine::query()
            ->whereHas('matchGroup', fn ($q) => $q->where('bank_reconciliation_id', $reconciliation->id))
            ->pluck('transaction_id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(BankReconciliation $reconciliation): array
    {
        return [
            'bank_net' => round($this->bankNet($reconciliation), 2),
            'book_net' => round($this->bookNet($reconciliation), 2),
            'difference' => $this->difference($reconciliation),
            'opening_balance_bank' => round((float) ($reconciliation->opening_balance_bank ?? 0), 2),
            'closing_balance_bank' => round((float) ($reconciliation->closing_balance_bank ?? 0), 2),
            'opening_balance_book' => round((float) ($reconciliation->opening_balance_book ?? 0), 2),
            'closing_balance_book' => round((float) ($reconciliation->closing_balance_book ?? 0), 2),
            'adjusted_bank' => $this->adjustedBank($reconciliation),
            'adjusted_book' => $this->adjustedBook($reconciliation),
            'unexplained' => $this->unexplained($reconciliation),
            'unmatched_bank_count' => $this->unmatchedBankCount($reconciliation),
            'unmatched_book_count' => $this->unmatchedBookCount($reconciliation),
            'is_balanced' => $this->isBalanced($reconciliation),
        ];
    }

    private function bankJournalEntry(Transaction $transaction, int $accountId): ?\App\Models\JournalEntry
    {
        return $transaction->journalEntries->firstWhere('account_id', $accountId);
    }
}
