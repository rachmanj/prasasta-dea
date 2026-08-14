<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** @return array<int, float> account_id => saldo (debit - credit) */
    public function accountBalances(?string $asOf = null): array
    {
        $query = JournalEntry::query()
            ->select('account_id', DB::raw('SUM(debit - credit) as balance'))
            ->groupBy('account_id');

        if ($asOf) {
            $query->whereHas('transaction', fn ($q) => $q->where('date', '<=', $asOf));
        }

        return $query->pluck('balance', 'account_id')->all();
    }

    public function accountBalance(int $accountId, ?string $asOf = null): float
    {
        return (float) ($this->accountBalances($asOf)[$accountId] ?? 0);
    }

    /** @return array<int, array{id:int, code:string, name:string, balance:float}> */
    public function cashBalances(?string $asOf = null): array
    {
        $accounts = Account::where('is_bank', true)
            ->orWhere('code', '1000')
            ->orderBy('code')
            ->get();

        $balances = $this->accountBalances($asOf);

        return $accounts->map(fn ($a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'balance' => round((float) ($balances[$a->id] ?? 0), 2),
        ])->all();
    }

    public function cashFlow(string $start, string $end): array
    {
        $cashIds = $this->cashAccountIds();

        $entries = JournalEntry::query()
            ->whereIn('account_id', $cashIds)
            ->whereHas('transaction', fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->with(['transaction' => fn ($q) => $q->where('type', '!=', 'transfer'), 'transaction.journalEntries.account'])
            ->get();

        $inflowByCategory = [];
        $outflowByCategory = [];
        $totalInflow = 0.0;
        $totalOutflow = 0.0;

        foreach ($entries as $entry) {
            $tx = $entry->transaction;
            if (! $tx || $tx->type === 'transfer') {
                continue;
            }

            $isInflow = (float) $entry->debit > 0;
            $amount = (float) ($isInflow ? $entry->debit : $entry->credit);

            foreach ($tx->journalEntries as $line) {
                if ($line->id === $entry->id) {
                    continue;
                }

                $key = $line->account->code . ' - ' . $line->account->name;

                if ($isInflow) {
                    $totalInflow += $amount;
                    $inflowByCategory[$key] = ($inflowByCategory[$key] ?? 0) + $amount;
                } else {
                    $totalOutflow += $amount;
                    $outflowByCategory[$key] = ($outflowByCategory[$key] ?? 0) + $amount;
                }
            }
        }

        return [
            'total_inflow' => round($totalInflow, 2),
            'total_outflow' => round($totalOutflow, 2),
            'net' => round($totalInflow - $totalOutflow, 2),
            'inflow_by_category' => $inflowByCategory,
            'outflow_by_category' => $outflowByCategory,
        ];
    }

    public function profitLoss(string $start, string $end): array
    {
        $revenue = $this->accountNet(Account::where('type', 'revenue')->pluck('id'), $start, $end);
        $expense = $this->accountNet(Account::where('type', 'expense')->pluck('id'), $start, $end);

        $totalRevenue = round(array_sum(array_column($revenue, 'net')), 2);
        $totalExpense = round(array_sum(array_column($expense, 'net')), 2);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'profit' => round($totalRevenue - $totalExpense, 2),
        ];
    }

    public function receivablesPayables(): array
    {
        $receivables = Bill::query()
            ->where('type', 'receivable')
            ->where('status', '!=', 'paid')
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $payables = Bill::query()
            ->where('type', 'payable')
            ->where('status', '!=', 'paid')
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        return [
            'receivables' => $receivables,
            'payables' => $payables,
            'total_receivable' => round((float) $receivables->sum('remaining'), 2),
            'total_payable' => round((float) $payables->sum('remaining'), 2),
        ];
    }

    private function accountNet($accountIds, string $start, string $end): array
    {
        return JournalEntry::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('transaction', fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->with('account')
            ->select('account_id', DB::raw('SUM(credit - debit) as net'))
            ->groupBy('account_id')
            ->get()
            ->map(fn ($e) => [
                'code' => $e->account->code,
                'name' => $e->account->name,
                'net' => round((float) $e->net, 2),
            ])
            ->all();
    }

    private function cashAccountIds()
    {
        return Account::where('is_bank', true)->pluck('id');
    }
}
