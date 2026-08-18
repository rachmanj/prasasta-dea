<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): Response
    {
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());

        return Inertia::render('Dashboard', [
            'start' => $start,
            'end' => $end,
            'cashBalances' => $reports->cashBalances(),
            'cashFlow' => $reports->cashFlow($start, $end),
            'receivablesPayables' => $reports->receivablesPayables(),
        ]);
    }
}
