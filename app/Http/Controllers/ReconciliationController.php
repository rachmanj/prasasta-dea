<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\ReconciliationMatchGroup;
use App\Services\ReconciliationBalanceService;
use App\Services\ReconciliationMatchingService;
use App\Services\ReconciliationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(): Response
    {
        $reconciliations = BankReconciliation::query()
            ->with('account')
            ->orderByDesc('period')
            ->paginate(20);

        return Inertia::render('Reconciliations/Index', [
            'reconciliations' => $reconciliations,
        ]);
    }

    public function create(): Response
    {
        $defaultAccount = Account::where('code', '1011')->first();

        return Inertia::render('Reconciliations/Create', [
            'bankAccounts' => Account::where('is_bank', true)->orderBy('code')->get(),
            'defaultAccountId' => $defaultAccount?->id,
        ]);
    }

    public function store(Request $request, ReconciliationService $service): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'period' => 'required|date',
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        try {
            $reconciliation = $service->createFromPdf(
                (int) $data['account_id'],
                $data['period'],
                $request->file('file'),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('reconciliations.show', $reconciliation)
            ->with('success', 'Rekening koran berhasil diunggah dan diparse.');
    }

    public function show(
        BankReconciliation $reconciliation,
        ReconciliationBalanceService $balanceService,
    ): Response {
        $reconciliation->load(['account', 'startedBy']);

        $bankLines = $reconciliation->bankLines()
            ->orderBy('line_order')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $bookLines = collect($balanceService->bookLines($reconciliation))->map(function (array $line) {
            $tx = $line['transaction'];

            return [
                'id' => $tx->id,
                'date' => $tx->date->toDateString(),
                'journal_no' => $tx->journal_no,
                'description' => $tx->description,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'net' => $line['net'],
                'matched_status' => $line['matched_status'],
            ];
        });

        $matchGroups = $reconciliation->matchGroups()
            ->with(['bankLines', 'transactions'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReconciliationMatchGroup $group) => [
                'id' => $group->id,
                'match_type' => $group->match_type,
                'bank_total' => (float) $group->bank_total,
                'book_total' => (float) $group->book_total,
                'difference' => (float) $group->difference,
                'bank_line_ids' => $group->bankLines->pluck('id')->all(),
                'transaction_ids' => $group->transactions->pluck('id')->all(),
            ]);

        return Inertia::render('Reconciliations/Show', [
            'reconciliation' => $reconciliation,
            'bankLines' => $bankLines,
            'bookLines' => $bookLines,
            'matchGroups' => $matchGroups,
            'balances' => $balanceService->statusPayload($reconciliation),
        ]);
    }

    public function autoMatch(
        BankReconciliation $reconciliation,
        ReconciliationMatchingService $matching,
    ): RedirectResponse {
        try {
            $count = $matching->autoMatch($reconciliation);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$count} grup berhasil dicocokkan otomatis.");
    }

    public function match(
        Request $request,
        BankReconciliation $reconciliation,
        ReconciliationMatchingService $matching,
    ): RedirectResponse {
        $data = $request->validate([
            'bank_line_ids' => 'required|array|min:1',
            'bank_line_ids.*' => 'integer|exists:bank_statement_lines,id',
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        try {
            $matching->manualMatch(
                $reconciliation,
                $data['bank_line_ids'],
                $data['transaction_ids'],
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Baris berhasil dicocokkan.');
    }

    public function unmatch(
        BankReconciliation $reconciliation,
        ReconciliationMatchGroup $group,
        ReconciliationMatchingService $matching,
    ): RedirectResponse {
        try {
            $matching->unmatchGroup($reconciliation, $group);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cocokkan dibatalkan.');
    }

    public function exclude(
        Request $request,
        BankReconciliation $reconciliation,
        BankStatementLine $line,
        ReconciliationService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $service->excludeLine($reconciliation, $line, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status baris bank diperbarui.');
    }

    public function complete(
        BankReconciliation $reconciliation,
        ReconciliationService $service,
    ): RedirectResponse {
        try {
            $service->complete($reconciliation);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rekonsiliasi berhasil diselesaikan.');
    }

    public function destroy(
        BankReconciliation $reconciliation,
        ReconciliationService $service,
    ): RedirectResponse {
        try {
            $service->destroy($reconciliation);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('reconciliations.index')
            ->with('success', 'Sesi rekonsiliasi dihapus.');
    }
}
