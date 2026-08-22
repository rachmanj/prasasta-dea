<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\CashOpnameController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\ProgramController;
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
    // Program pelatihan
    Route::get('programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::get('programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::delete('programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
        Route::post('programs/{program}/participants', [ProgramController::class, 'storeParticipant'])->name('programs.participants.store');
        Route::patch('participants/{participant}', [ProgramController::class, 'updateParticipant'])->name('participants.update');
        Route::delete('participants/{participant}', [ProgramController::class, 'destroyParticipant'])->name('participants.destroy');
    });
    Route::get('programs/{program}', [ProgramController::class, 'show'])->name('programs.show');

    // Master data — accounts
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    });

    // Master data — contacts
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    });

    Route::resource('users', UserController::class)->except(['create', 'edit', 'show'])->middleware('role:admin');

    Route::get('opening-balances', [OpeningBalanceController::class, 'index'])
        ->name('opening-balances.index')
        ->middleware('role:admin');
    Route::post('opening-balances', [OpeningBalanceController::class, 'store'])
        ->name('opening-balances.store')
        ->middleware('role:admin');

    // Transaksi
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    });

    // Tagihan (hutang / piutang)
    Route::get('bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('bills/create', [BillController::class, 'create'])->name('bills.create')->middleware('role:admin|bendahara');
    Route::get('bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('bills', [BillController::class, 'store'])->name('bills.store');
        Route::post('bills/{bill}/payments', [BillController::class, 'recordPayment'])->name('bills.payments.store');
        Route::delete('bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');
    });

    // Aset tetap
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create')->middleware('role:admin|bendahara');
    Route::get('assets/depreciation', [AssetController::class, 'depreciation'])->name('assets.depreciation');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::post('assets/depreciation', [AssetController::class, 'postDepreciation'])->name('assets.depreciation.post');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    // Kas bon
    Route::get('cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances.index');
    Route::get('cash-advances/create', [CashAdvanceController::class, 'create'])->name('cash-advances.create')->middleware('role:admin|bendahara');
    Route::get('cash-advances/{advance}/realize', [CashAdvanceController::class, 'realizeForm'])->name('cash-advances.realize.create')->middleware('role:admin|bendahara');
    Route::get('cash-advances/{advance}', [CashAdvanceController::class, 'show'])->name('cash-advances.show');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('cash-advances', [CashAdvanceController::class, 'store'])->name('cash-advances.store');
        Route::post('cash-advances/{advance}/realize', [CashAdvanceController::class, 'realize'])->name('cash-advances.realize');
        Route::delete('cash-advances/{advance}', [CashAdvanceController::class, 'destroy'])->name('cash-advances.destroy');
    });

    // Rekonsiliasi bank
    Route::get('reconciliations', [ReconciliationController::class, 'index'])->name('reconciliations.index');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::get('reconciliations/create', [ReconciliationController::class, 'create'])->name('reconciliations.create');
        Route::post('reconciliations', [ReconciliationController::class, 'store'])->name('reconciliations.store');
        Route::post('reconciliations/{reconciliation}/auto-match', [ReconciliationController::class, 'autoMatch'])->name('reconciliations.auto-match');
        Route::post('reconciliations/{reconciliation}/match', [ReconciliationController::class, 'match'])->name('reconciliations.match');
        Route::post('reconciliations/{reconciliation}/unmatch/{group}', [ReconciliationController::class, 'unmatch'])->name('reconciliations.unmatch');
        Route::post('reconciliations/{reconciliation}/exclude/{line}', [ReconciliationController::class, 'exclude'])->name('reconciliations.exclude');
        Route::post('reconciliations/{reconciliation}/complete', [ReconciliationController::class, 'complete'])->name('reconciliations.complete');
        Route::delete('reconciliations/{reconciliation}', [ReconciliationController::class, 'destroy'])->name('reconciliations.destroy');
    });
    Route::get('reconciliations/{reconciliation}', [ReconciliationController::class, 'show'])->name('reconciliations.show');

    // Kas opname
    Route::get('cash-opnames', [CashOpnameController::class, 'index'])->name('cash-opnames.index');
    Route::get('cash-opnames/create', [CashOpnameController::class, 'create'])->name('cash-opnames.create')->middleware('role:admin|bendahara');
    Route::get('cash-opnames/{opname}', [CashOpnameController::class, 'show'])->name('cash-opnames.show');
    Route::get('cash-opnames/{opname}/pdf', [CashOpnameController::class, 'pdf'])->name('cash-opnames.pdf');
    Route::middleware('role:admin|bendahara')->group(function () {
        Route::post('cash-opnames', [CashOpnameController::class, 'store'])->name('cash-opnames.store');
        Route::post('cash-opnames/{opname}/adjust', [CashOpnameController::class, 'adjust'])->name('cash-opnames.adjust');
    });

    // Laporan
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('reports/receivables-payables', [ReportController::class, 'receivablesPayables'])->name('reports.receivables-payables');
    Route::get('reports/general-ledger', [ReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('reports/export/cash-flow', [ReportController::class, 'exportCashFlow'])->name('reports.export.cash-flow');
    Route::get('reports/export/general-ledger', [ReportController::class, 'exportGeneralLedger'])->name('reports.export.general-ledger');
    Route::get('reports/export/profit-loss', [ReportController::class, 'exportProfitLoss'])->name('reports.export.profit-loss');
    Route::get('reports/export/receivables-payables', [ReportController::class, 'exportReceivablesPayables'])->name('reports.export.receivables-payables');
});

require __DIR__ . '/auth.php';
