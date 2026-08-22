<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\JournalEntry;
use App\Models\Program;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
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
        $cashIds = $this->cashAccountIds()->all();

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

            // Offset account(s) = the non-cash line(s) in the same transaction.
            $keys = [];
            foreach ($tx->journalEntries as $line) {
                if ($line->id === $entry->id) {
                    continue;
                }
                if (in_array($line->account_id, $cashIds)) {
                    continue; // skip other cash lines (multi-cash / transfer-like journals)
                }
                $keys[] = $line->account->code . ' - ' . $line->account->name;
            }
            if (empty($keys)) {
                $keys[] = 'Lainnya';
            }

            if ($isInflow) {
                $totalInflow += $amount;
                foreach ($keys as $key) {
                    $inflowByCategory[$key] = ($inflowByCategory[$key] ?? 0) + $amount;
                }
            } else {
                $totalOutflow += $amount;
                foreach ($keys as $key) {
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

        // accountNet returns credit - debit, so expense nets are negative; flip to positive for display.
        $expense = array_map(
            fn ($e) => ['code' => $e['code'], 'name' => $e['name'], 'net' => round(-(float) $e['net'], 2)],
            $expense,
        );

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

    public function generalLedger(int $accountId, string $start, string $end): array
    {
        $openingBalance = round($this->accountBalance(
            $accountId,
            Carbon::parse($start)->subDay()->toDateString()
        ), 2);

        $entries = JournalEntry::query()
            ->where('account_id', $accountId)
            ->whereHas('transaction', fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->with('transaction')
            ->join('transactions', 'journal_entries.transaction_id', '=', 'transactions.id')
            ->orderBy('transactions.date')
            ->orderBy('transactions.id')
            ->select('journal_entries.*')
            ->get();

        $balance = $openingBalance;
        $rows = [];

        foreach ($entries as $entry) {
            $debit = (float) $entry->debit;
            $credit = (float) $entry->credit;
            $balance = round($balance + $debit - $credit, 2);

            $rows[] = [
                'date' => $entry->transaction->date->format('Y-m-d'),
                'journal_no' => $entry->transaction->journal_no,
                'description' => $entry->transaction->description ?? $entry->description ?? '',
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => $balance,
            ];
        }

        return [
            'opening_balance' => $openingBalance,
            'entries' => $rows,
            'ending_balance' => $balance,
        ];
    }

    public function programSummaries(): array
    {
        $service = app(ProgramService::class);

        return Program::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Program $program) use ($service) {
                $pl = $service->profitLoss($program);

                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'type' => $program->type,
                    'start_date' => $program->start_date?->format('Y-m-d'),
                    'end_date' => $program->end_date?->format('Y-m-d'),
                    'status' => $program->status,
                    'revenue' => $pl['revenue'],
                    'expense' => $pl['expense'],
                    'profit' => $pl['profit'],
                ];
            })
            ->all();
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
        return Account::where('is_bank', true)
            ->orWhere('code', '1000')
            ->pluck('id');
    }
}
