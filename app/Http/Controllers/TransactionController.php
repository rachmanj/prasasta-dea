<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Exports\TransactionsExport;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->input('type', 'receipt');

        $transactions = Transaction::query()
            ->where('type', $type)
            ->with(['journalEntries.account', 'user'])
            ->when($request->input('from'), fn ($q, $f) => $q->where('date', '>=', $f))
            ->when($request->input('to'), fn ($q, $t) => $q->where('date', '<=', $t))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'from', 'to']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Transactions/Form', array_merge([
            'transaction' => null,
            'type' => $request->input('type', 'receipt'),
        ], $this->formOptions()));
    }

    public function store(Request $request, TransactionService $service): RedirectResponse
    {
        $data = $this->validated($request);

        $service->create($data);

        return redirect()->route('transactions.index', ['type' => $data['type']])
            ->with('success', 'Transaksi berhasil disimpan.');
    }

    public function edit(Transaction $transaction): Response
    {
        $transaction->load('journalEntries.account');

        return Inertia::render('Transactions/Form', array_merge([
            'transaction' => $transaction,
            'type' => $transaction->type,
        ], $this->formOptions()));
    }

    public function update(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $data = $this->validated($request);

        $service->update($transaction, $data);

        return redirect()->route('transactions.index', ['type' => $data['type']])
            ->with('success', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Transaction $transaction, TransactionService $service): RedirectResponse
    {
        if ($transaction->billPayments()->exists()) {
            return back()->with('error', 'Transaksi ini terkait pembayaran tagihan. Hapus lewat menu tagihan.');
        }

        $service->delete($transaction);

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $type = $request->input('type', 'receipt');

        $transactions = Transaction::query()
            ->where('type', $type)
            ->with(['journalEntries.account'])
            ->when($request->input('from'), fn ($q, $f) => $q->where('date', '>=', $f))
            ->when($request->input('to'), fn ($q, $t) => $q->where('date', '<=', $t))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($transactions as $tx) {
            foreach ($tx->journalEntries as $entry) {
                $rows[] = [
                    $tx->journal_no,
                    $tx->date->format('Y-m-d'),
                    $tx->type,
                    $tx->description,
                    $entry->account->code . ' ' . $entry->account->name,
                    (float) $entry->debit,
                    (float) $entry->credit,
                ];
            }
        }

        return Excel::download(new TransactionsExport($rows), "transaksi-{$type}.xlsx");
    }

    private function validated(Request $request): array
    {
        $type = $request->input('type');

        $rules = [
            'type' => 'required|in:receipt,payment,transfer,journal',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
            'ref_no' => 'nullable|string|max:100',
            'program_id' => 'nullable|exists:programs,id',
        ];

        if ($type === 'journal') {
            $rules['lines'] = 'required|array|min:2';
            $rules['lines.*.account_id'] = 'required|exists:accounts,id';
            $rules['lines.*.debit'] = 'nullable|numeric|min:0';
            $rules['lines.*.credit'] = 'nullable|numeric|min:0';
        } elseif ($type === 'transfer') {
            $rules['from_account_id'] = 'required|exists:accounts,id';
            $rules['to_account_id'] = 'required|exists:accounts,id|different:from_account_id';
            $rules['amount'] = 'required|numeric|min:0.01';
        } else {
            $rules['account_id'] = 'required|exists:accounts,id';
            $rules['category_id'] = 'required|exists:accounts,id';
            $rules['amount'] = 'required|numeric|min:0.01';
        }

        $data = $request->validate($rules);

        if ($type === 'journal') {
            $totalDebit = array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $data['lines']));
            $totalCredit = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $data['lines']));

            if (abs($totalDebit - $totalCredit) > 0.01 || $totalDebit <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lines' => 'Total debit harus sama dengan total credit.',
                ]);
            }
        }

        return $data;
    }

    private function formOptions(): array
    {
        return [
            'cashAccounts' => Account::where('is_bank', true)->orWhere('code', '1000')->orderBy('code')->get(),
            'revenueAccounts' => Account::where('type', 'revenue')->orderBy('code')->get(),
            'expenseAccounts' => Account::where('type', 'expense')->orderBy('code')->get(),
            'allAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'programs' => \App\Models\Program::orderByDesc('start_date')->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
