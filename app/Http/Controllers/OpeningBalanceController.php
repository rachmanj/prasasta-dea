<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalanceController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        $hasSaldoAwal = Transaction::where('type', 'journal')
            ->where('description', 'Saldo Awal')
            ->exists();

        return Inertia::render('OpeningBalances/Index', [
            'accounts' => $accounts,
            'hasSaldoAwal' => $hasSaldoAwal,
        ]);
    }

    public function store(Request $request, TransactionService $transactions): RedirectResponse
    {
        $data = $request->validate([
            'balances' => 'required|array',
            'balances.*' => 'numeric|min:0',
            'date' => 'required|date',
        ]);

        DB::transaction(function () use ($data, $transactions) {
            $existing = Transaction::where('type', 'journal')
                ->where('description', 'Saldo Awal')
                ->first();

            if ($existing) {
                $transactions->delete($existing);
            }

            $accounts = Account::all()->keyBy('id');
            $lines = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($data['balances'] as $accountId => $balance) {
                $amount = (float) $balance;
                if ($amount <= 0) {
                    continue;
                }

                $account = $accounts->get((int) $accountId);
                if (! $account) {
                    continue;
                }

                if (in_array($account->type, ['asset', 'expense'], true)) {
                    $lines[] = [
                        'account_id' => $account->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'Saldo Awal',
                    ];
                    $totalDebit += $amount;
                } elseif (in_array($account->type, ['liability', 'equity', 'revenue'], true)) {
                    $lines[] = [
                        'account_id' => $account->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Saldo Awal',
                    ];
                    $totalCredit += $amount;
                }
            }

            $retained = Account::where('code', '3100')->firstOrFail();

            if ($totalDebit > $totalCredit) {
                $diff = round($totalDebit - $totalCredit, 2);
                $lines[] = [
                    'account_id' => $retained->id,
                    'debit' => 0,
                    'credit' => $diff,
                    'description' => 'Saldo Awal',
                ];
            } elseif ($totalCredit > $totalDebit) {
                $diff = round($totalCredit - $totalDebit, 2);
                $lines[] = [
                    'account_id' => $retained->id,
                    'debit' => $diff,
                    'credit' => 0,
                    'description' => 'Saldo Awal',
                ];
            }

            $transactions->create([
                'type' => 'journal',
                'date' => $data['date'],
                'description' => 'Saldo Awal',
                'lines' => $lines,
            ]);

            foreach ($accounts as $account) {
                $account->update([
                    'opening_balance' => (float) ($data['balances'][$account->id] ?? 0),
                ]);
            }
        });

        return back()->with('success', 'Saldo awal berhasil disimpan.');
    }
}
