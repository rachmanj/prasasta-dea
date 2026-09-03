<?php

namespace App\Http\Controllers;

use App\Exports\CashFlowExport;
use App\Exports\GeneralLedgerExport;
use App\Exports\ProfitLossExport;
use App\Exports\ProgramProfitLossExport;
use App\Exports\ReceivablesPayablesExport;
use App\Models\Account;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function cashFlow(Request $request, ReportService $reports): Response
    {
        [$start, $end] = $this->period($request);

        return Inertia::render('Reports/CashFlow', [
            'start' => $start,
            'end' => $end,
            'data' => $reports->cashFlow($start, $end),
        ]);
    }

    public function profitLoss(Request $request, ReportService $reports): Response
    {
        [$start, $end] = $this->period($request);

        return Inertia::render('Reports/ProfitLoss', [
            'start' => $start,
            'end' => $end,
            'data' => $reports->profitLoss($start, $end),
        ]);
    }

    public function receivablesPayables(ReportService $reports): Response
    {
        return Inertia::render('Reports/ReceivablesPayables', [
            'data' => $reports->receivablesPayables(),
        ]);
    }

    public function programProfitLoss(ReportService $reports): Response
    {
        return Inertia::render('Reports/ProgramProfitLoss', [
            'data' => $reports->programProfitLoss(),
        ]);
    }

    public function generalLedger(Request $request, ReportService $reports): Response
    {
        [$start, $end] = $this->period($request);
        $accountId = $this->resolveAccountId($request);

        $accounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Reports/GeneralLedger', [
            'accounts' => $accounts,
            'accountId' => $accountId,
            'start' => $start,
            'end' => $end,
            'data' => $reports->generalLedger($accountId, $start, $end),
        ]);
    }

    public function exportCashFlow(Request $request, ReportService $reports): BinaryFileResponse
    {
        [$start, $end] = $this->period($request);

        return Excel::download(
            new CashFlowExport($reports->cashFlow($start, $end), $start, $end),
            "arus-kas-{$start}-{$end}.xlsx"
        );
    }

    public function exportProfitLoss(Request $request, ReportService $reports): BinaryFileResponse
    {
        [$start, $end] = $this->period($request);

        return Excel::download(
            new ProfitLossExport($reports->profitLoss($start, $end), $start, $end),
            "laba-rugi-{$start}-{$end}.xlsx"
        );
    }

    public function exportReceivablesPayables(ReportService $reports): BinaryFileResponse
    {
        return Excel::download(
            new ReceivablesPayablesExport($reports->receivablesPayables()),
            'hutang-piutang.xlsx'
        );
    }

    public function exportProgramProfitLoss(ReportService $reports): BinaryFileResponse
    {
        return Excel::download(
            new ProgramProfitLossExport($reports->programProfitLoss()),
            'laba-rugi-program.xlsx'
        );
    }

    public function exportGeneralLedger(Request $request, ReportService $reports): BinaryFileResponse
    {
        [$start, $end] = $this->period($request);
        $accountId = $this->resolveAccountId($request);
        $account = Account::findOrFail($accountId);

        return Excel::download(
            new GeneralLedgerExport(
                $reports->generalLedger($accountId, $start, $end),
                $start,
                $end,
                ['code' => $account->code, 'name' => $account->name]
            ),
            "buku-besar-{$start}-{$end}.xlsx"
        );
    }

    private function resolveAccountId(Request $request): int
    {
        $accountId = $request->input('account_id');

        if (! $accountId) {
            $accountId = Account::where('is_active', true)->orderBy('code')->first()?->id;
        }

        $request->merge(['account_id' => $accountId]);
        $request->validate(['account_id' => 'required|exists:accounts,id']);

        return (int) $accountId;
    }

    private function period(Request $request): array
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());

        return [$start, $end];
    }
}
