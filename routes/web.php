<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Master data
    Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'show']);
    Route::resource('contacts', ContactController::class)->except(['create', 'edit', 'show']);
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show'])->middleware('role:admin');

    Route::get('opening-balances', [OpeningBalanceController::class, 'index'])
        ->name('opening-balances.index')
        ->middleware('role:admin');
    Route::post('opening-balances', [OpeningBalanceController::class, 'store'])
        ->name('opening-balances.store')
        ->middleware('role:admin');

    // Transaksi
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Tagihan (hutang / piutang)
    Route::get('bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::post('bills/{bill}/payments', [BillController::class, 'recordPayment'])->name('bills.payments.store');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');

    // Rekonsiliasi bank
    Route::get('reconciliations', [ReconciliationController::class, 'index'])->name('reconciliations.index');
    Route::post('reconciliations', [ReconciliationController::class, 'store'])->name('reconciliations.store');
    Route::post('reconciliations/import', [ReconciliationController::class, 'import'])->name('reconciliations.import');
    Route::post('reconciliations/{line}/match', [ReconciliationController::class, 'match'])->name('reconciliations.match');
    Route::post('reconciliations/{line}/unmatch', [ReconciliationController::class, 'unmatch'])->name('reconciliations.unmatch');

    // Laporan
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('reports/receivables-payables', [ReportController::class, 'receivablesPayables'])->name('reports.receivables-payables');
    Route::get('reports/export/cash-flow', [ReportController::class, 'exportCashFlow'])->name('reports.export.cash-flow');
    Route::get('reports/export/profit-loss', [ReportController::class, 'exportProfitLoss'])->name('reports.export.profit-loss');
    Route::get('reports/export/receivables-payables', [ReportController::class, 'exportReceivablesPayables'])->name('reports.export.receivables-payables');
});

require __DIR__ . '/auth.php';
