<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Services\ReportService;
use App\Services\TransactionService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
    }

    public function test_general_ledger_returns_correct_balances_and_entries(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();
        $expense = Account::where('code', '5100')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-10',
            'description' => 'Penerimaan siswa',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => 1000000,
        ]);

        app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-08-20',
            'description' => 'Honor instruktur',
            'account_id' => $cash->id,
            'category_id' => $expense->id,
            'amount' => 300000,
        ]);

        $ledger = app(ReportService::class)->generalLedger($cash->id, '2026-08-01', '2026-08-31');

        $this->assertEquals(0, $ledger['opening_balance']);
        $this->assertCount(2, $ledger['entries']);

        $this->assertEquals('2026-08-10', $ledger['entries'][0]['date']);
        $this->assertEquals(1000000, $ledger['entries'][0]['debit']);
        $this->assertEquals(0, $ledger['entries'][0]['credit']);
        $this->assertEquals(1000000, $ledger['entries'][0]['balance']);

        $this->assertEquals('2026-08-20', $ledger['entries'][1]['date']);
        $this->assertEquals(0, $ledger['entries'][1]['debit']);
        $this->assertEquals(300000, $ledger['entries'][1]['credit']);
        $this->assertEquals(700000, $ledger['entries'][1]['balance']);

        $this->assertEquals(700000, $ledger['ending_balance']);
    }
}
