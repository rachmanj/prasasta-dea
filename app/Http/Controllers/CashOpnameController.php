<?php

namespace App\Http\Controllers;

use App\Models\CashOpname;
use App\Services\CashOpnameService;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CashOpnameController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $opnames = CashOpname::query()
            ->with('preparedBy')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('CashOpnames/Index', [
            'opnames' => $opnames,
            'canCreate' => auth()->user()?->hasAnyRole(['admin', 'bendahara']) ?? false,
        ]);
    }

    public function create(Request $request, CashOpnameService $service): InertiaResponse
    {
        $date = $request->input('date', now()->toDateString());
        $cashAccount = \App\Models\Account::where('code', '1000')->firstOrFail();
        $bookBalance = app(\App\Services\ReportService::class)->accountBalance($cashAccount->id, $date);

        return Inertia::render('CashOpnames/Form', [
            'denominations' => CashOpnameService::DENOMINATIONS,
            'bookBalance' => $bookBalance,
            'previewDate' => $date,
            'canCreate' => true,
        ]);
    }

    public function store(Request $request, CashOpnameService $service): RedirectResponse
    {
        $data = $request->validate([
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array',
            'lines.*.denomination' => 'required|integer',
            'lines.*.type' => 'required|in:banknote,coin',
            'lines.*.units' => 'required|integer|min:0',
        ]);

        $opname = $service->create($data);

        return redirect()->route('cash-opnames.show', $opname)
            ->with('success', 'Kas opname berhasil disimpan.');
    }

    public function show(CashOpname $opname): InertiaResponse
    {
        $opname->load(['lines', 'preparedBy.roles', 'account', 'adjustmentTransaction']);

        return Inertia::render('CashOpnames/Show', [
            'opname' => $opname,
            'terbilang' => Terbilang::rupiah($opname->physical_balance),
            'canAdjust' => auth()->user()?->hasAnyRole(['admin', 'bendahara']) ?? false,
        ]);
    }

    public function pdf(CashOpname $opname): Response
    {
        $opname->load(['lines', 'preparedBy.roles', 'account']);

        $preparedRole = $this->resolveRoleLabel($opname);

        $pdf = Pdf::loadView('pdf.cash-opname', [
            'opname' => $opname,
            'terbilang' => Terbilang::rupiah($opname->physical_balance),
            'preparedRole' => $preparedRole,
            'banknotes' => $opname->lines->where('type', 'banknote')->values(),
            'coins' => $opname->lines->where('type', 'coin')->values(),
        ])->setPaper('a4');

        $filename = $opname->number.'.pdf';

        return $pdf->stream($filename);
    }

    public function adjust(CashOpname $opname, CashOpnameService $service): RedirectResponse
    {
        try {
            $service->adjust($opname);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cash-opnames.show', $opname)
            ->with('success', 'Penyesuaian selisih berhasil diposting.');
    }

    private function resolveRoleLabel(CashOpname $opname): string
    {
        $user = $opname->preparedBy;
        if (! $user) {
            return '';
        }

        if ($user->hasRole('bendahara')) {
            return 'Bendahara';
        }
        if ($user->hasRole('admin')) {
            return 'Admin';
        }
        if ($user->hasRole('pengurus')) {
            return 'Pengurus';
        }

        return '';
    }
}
