<?php

namespace App\Http\Controllers;

use App\Exports\CashFlowExport;
use App\Exports\ProfitLossExport;
use App\Exports\ReceivablesPayablesExport;
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

    private function period(Request $request): array
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());

        return [$start, $end];
    }
}
