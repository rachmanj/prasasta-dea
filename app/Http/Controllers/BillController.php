<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Contact;
use App\Services\BillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->input('type', 'receivable');

        $bills = Bill::query()
            ->where('type', $type)
            ->with('contact')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Bills/Index', [
            'bills' => $bills,
            'filters' => $request->only(['type', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $type = $request->input('type', 'receivable');

        return Inertia::render('Bills/Form', [
            'bill' => null,
            'type' => $type,
            'contacts' => Contact::orderBy('name')->get(),
            'offsetAccounts' => Account::where('type', $type === 'receivable' ? 'revenue' : 'expense')->orderBy('code')->get(),
            'cashAccounts' => Account::where('is_bank', true)->orWhere('code', '1000')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, BillService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:receivable,payable',
            'contact_id' => 'required|exists:contacts,id',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'due_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
        ]);

        $service->create($data);

        return redirect()->route('bills.index', ['type' => $data['type']])
            ->with('success', 'Tagihan berhasil dibuat.');
    }

    public function show(Bill $bill): Response
    {
        $bill->load([
            'contact',
            'account',
            'transaction.journalEntries.account',
            'payments.transaction.journalEntries.account',
        ]);

        return Inertia::render('Bills/Show', [
            'bill' => $bill,
            'cashAccounts' => Account::where('is_bank', true)->orWhere('code', '1000')->orderBy('code')->get(),
        ]);
    }

    public function recordPayment(Request $request, Bill $bill, BillService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
        ]);

        try {
            $service->recordPayment($bill, $data);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function destroy(Bill $bill, BillService $service): RedirectResponse
    {
        try {
            $service->delete($bill);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tagihan berhasil dihapus.');
    }
}
