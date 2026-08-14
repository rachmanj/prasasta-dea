<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\BankStatementImporter;
use App\Services\ReportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request, ReportService $reports): Response
    {
        $bankAccounts = Account::where('is_bank', true)->orderBy('code')->get();
        $accountId = $request->input('account_id');

        $lines = collect();
        $candidates = collect();

        if ($accountId) {
            $lines = BankStatementLine::where('account_id', $accountId)
                ->with('transaction')
                ->orderBy('date')
                ->get();

            $candidates = Transaction::whereIn('type', ['receipt', 'payment'])
                ->whereDoesntHave('statementLines')
                ->whereHas('journalEntries', fn ($q) => $q->where('account_id', $accountId))
                ->with('journalEntries.account')
                ->orderByDesc('date')
                ->limit(200)
                ->get();
        }

        return Inertia::render('Reconciliations/Index', [
            'bankAccounts' => $bankAccounts,
            'selectedAccount' => $accountId ? Account::find($accountId) : null,
            'lines' => $lines,
            'candidates' => $candidates,
            'balances' => $reports->cashBalances(),
        ]);
    }

    public function import(Request $request, BankStatementImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $parsed = $importer->parse($data['file']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $imported = 0;
        $matched = 0;

        foreach ($parsed as $line) {
            $line['account_id'] = $data['account_id'];

            $exists = BankStatementLine::where('account_id', $data['account_id'])
                ->where('source_ref', $line['source_ref'])
                ->exists();

            if ($exists) {
                continue;
            }

            $record = BankStatementLine::create($line);
            $imported++;

            if ($this->autoMatch($record)) {
                $matched++;
            }
        }

        return redirect()->route('reconciliations.index', ['account_id' => $data['account_id']])
            ->with('success', "{$imported} baris diimpor, {$matched} otomatis cocok.");
    }

    public function match(Request $request, BankStatementLine $line): RedirectResponse
    {
        $data = $request->validate(['transaction_id' => 'required|exists:transactions,id']);

        $line->update(['transaction_id' => $data['transaction_id'], 'is_matched' => true]);

        return back()->with('success', 'Statement berhasil dicocokkan.');
    }

    public function unmatch(BankStatementLine $line): RedirectResponse
    {
        $line->update(['transaction_id' => null, 'is_matched' => false]);

        return back();
    }

    private function autoMatch(BankStatementLine $line): bool
    {
        $amount = (float) $line->amount;
        $target = abs($amount);
        $type = $amount >= 0 ? 'receipt' : 'payment';

        $candidate = Transaction::where('type', $type)
            ->where('date', $line->date->toDateString())
            ->whereDoesntHave('statementLines')
            ->whereHas('journalEntries', function ($q) use ($line, $target) {
                $q->where('account_id', $line->account_id)
                    ->where(function ($qq) use ($target) {
                        $qq->where('debit', $target)->orWhere('credit', $target);
                    });
            })
            ->first();

        if ($candidate) {
            $line->update(['transaction_id' => $candidate->id, 'is_matched' => true]);

            return true;
        }

        return false;
    }
}
