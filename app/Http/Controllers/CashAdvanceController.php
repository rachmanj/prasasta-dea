<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashAdvance;
use App\Models\Contact;
use App\Models\Program;
use App\Services\CashAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAdvanceController extends Controller
{
    public function index(Request $request): Response
    {
        $advances = CashAdvance::query()
            ->with('contact')
            ->when($request->input('contact_id'), fn ($q, $id) => $q->where('contact_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('CashAdvances/Index', [
            'advances' => $advances,
            'employees' => Contact::where('type', 'employee')->orderBy('name')->get(),
            'filters' => $request->only(['contact_id', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CashAdvances/Form', [
            'employees' => Contact::where('type', 'employee')->orderBy('name')->get(),
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CashAdvanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'program_id' => 'nullable|exists:programs,id',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        $contact = Contact::findOrFail($data['contact_id']);
        if ($contact->type !== 'employee') {
            return back()->with('error', 'Kontak harus bertipe karyawan.');
        }

        $service->create($data);

        return redirect()->route('cash-advances.index')
            ->with('success', 'Kas bon berhasil dibuat.');
    }

    public function show(CashAdvance $advance): Response
    {
        $advance->load([
            'contact',
            'program',
            'transaction.journalEntries.account',
            'realizations.program',
            'realizations.transaction.journalEntries.account',
        ]);

        return Inertia::render('CashAdvances/Show', [
            'advance' => $advance,
        ]);
    }

    public function realizeForm(CashAdvance $advance): Response|RedirectResponse
    {
        if ($advance->status === 'settled') {
            return redirect()->route('cash-advances.show', $advance)
                ->with('error', 'Kas bon sudah lunas.');
        }

        return Inertia::render('CashAdvances/Realize', [
            'advance' => $advance->load('contact'),
            'expenseAccounts' => Account::where('type', 'expense')->orderBy('code')->get(),
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function realize(Request $request, CashAdvance $advance, CashAdvanceService $service): RedirectResponse
    {
        if ($advance->status === 'settled') {
            return back()->with('error', 'Kas bon sudah lunas.');
        }

        $data = $request->validate([
            'date' => 'required|date',
            'program_id' => 'nullable|exists:programs,id',
            'description' => 'nullable|string|max:255',
            'lines' => 'array',
            'lines.*.account_id' => 'required_with:lines|exists:accounts,id',
            'lines.*.amount' => 'required_with:lines|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
            'returned_amount' => 'nullable|numeric|min:0',
        ]);

        $data['lines'] = collect($data['lines'] ?? [])
            ->filter(fn ($l) => (float) ($l['amount'] ?? 0) > 0)
            ->values()
            ->all();

        try {
            $service->realize($advance, $data);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-advances.show', $advance)
            ->with('success', 'Realisasi kas bon berhasil dicatat.');
    }

    public function destroy(CashAdvance $advance, CashAdvanceService $service): RedirectResponse
    {
        try {
            $service->delete($advance);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-advances.index')
            ->with('success', 'Kas bon berhasil dihapus.');
    }
}
