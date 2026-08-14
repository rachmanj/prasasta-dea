<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): Response
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return Inertia::render('Dashboard', [
            'cashBalances' => $reports->cashBalances(),
            'cashFlow' => $reports->cashFlow($start, $end),
            'receivablesPayables' => $reports->receivablesPayables(),
        ]);
    }
}
