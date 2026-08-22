<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\User;
use App\Services\ReconciliationBalanceService;
use App\Services\ReconciliationMatchingService;
use App\Services\ReconciliationService;
use App\Services\TransactionService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Account $bankAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->bankAccount = Account::where('code', '1011')->first();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('bendahara');
        $this->actingAs($this->user);
    }

    public function test_sign_convention_opposite_polarity_sums_to_zero(): void
    {
        $reconciliation = $this->createManualReconciliation('2026-07-01');

        $bankLine = BankStatementLine::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'transaction_date' => '2026-07-06',
            'description' => 'Tarik tunai',
            'debit' => 1000000,
            'credit' => 0,
            'matched_status' => BankStatementLine::MATCH_UNMATCHED,
        ]);

        $expense = Account::where('code', '5700')->first();
        $payment = app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-07-06',
            'description' => 'Bayar operasional',
            'account_id' => $this->bankAccount->id,
            'category_id' => $expense->id,
            'amount' => 1000000,
        ]);

        $balance = app(ReconciliationBalanceService::class);

        $this->assertSame(1000000.0, $balance->bankNet($reconciliation));
        $this->assertSame(-1000000.0, $balance->bookNet($reconciliation));
        $this->assertSame(0.0, $balance->difference($reconciliation));
        $this->assertFalse($balance->isBalanced($reconciliation));

        app(ReconciliationMatchingService::class)->manualMatch(
            $reconciliation,
            [$bankLine->id],
            [$payment->id],
        );

        $this->assertTrue($balance->isBalanced($reconciliation->fresh()));
    }

    public function test_split_match_many_book_to_one_bank(): void
    {
        $reconciliation = $this->createManualReconciliation('2026-07-01');
        $expense = Account::where('code', '5700')->first();

        $bankLine = BankStatementLine::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'transaction_date' => '2026-07-10',
            'description' => 'Tarik tunai gabungan',
            'debit' => 300000,
            'credit' => 0,
            'matched_status' => BankStatementLine::MATCH_UNMATCHED,
        ]);

        $payments = [];
        foreach ([100000, 100000, 100000] as $i => $amount) {
            $payments[] = app(TransactionService::class)->create([
                'type' => 'payment',
                'date' => '2026-07-09',
                'description' => 'Bayar '.$i,
                'account_id' => $this->bankAccount->id,
                'category_id' => $expense->id,
                'amount' => $amount,
            ]);
        }

        $matched = app(ReconciliationMatchingService::class)->autoMatch($reconciliation);

        $this->assertGreaterThanOrEqual(1, $matched);
        $this->assertTrue(app(ReconciliationBalanceService::class)->isBalanced($reconciliation->fresh()));
        $this->assertDatabaseHas('reconciliation_match_groups', [
            'bank_reconciliation_id' => $reconciliation->id,
            'match_type' => 'auto',
            'difference' => 0,
        ]);
    }

    public function test_statement_unexplained_is_zero_when_balanced(): void
    {
        $reconciliation = BankReconciliation::create([
            'account_id' => $this->bankAccount->id,
            'period' => '2026-07-01',
            'opening_balance_bank' => 1000000,
            'closing_balance_bank' => 500000,
            'opening_balance_book' => 1000000,
            'closing_balance_book' => 500000,
            'status' => BankReconciliation::STATUS_IN_REVIEW,
            'started_by' => $this->user->id,
        ]);

        $bankLine = BankStatementLine::create([
            'bank_reconciliation_id' => $reconciliation->id,
            'transaction_date' => '2026-07-15',
            'description' => 'Biaya adm',
            'debit' => 500000,
            'credit' => 0,
            'matched_status' => BankStatementLine::MATCH_UNMATCHED,
        ]);

        $expense = Account::where('code', '5700')->first();
        $payment = app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-07-15',
            'description' => 'Biaya adm',
            'account_id' => $this->bankAccount->id,
            'category_id' => $expense->id,
            'amount' => 500000,
        ]);

        app(ReconciliationMatchingService::class)->manualMatch(
            $reconciliation,
            [$bankLine->id],
            [$payment->id],
        );

        $balance = app(ReconciliationBalanceService::class);
        $this->assertSame(0.0, $balance->unexplained($reconciliation->fresh()));
        $this->assertTrue($balance->isBalanced($reconciliation->fresh()));
    }

    public function test_carry_forward_note_from_prior_outstanding(): void
    {
        $prior = BankReconciliation::create([
            'account_id' => $this->bankAccount->id,
            'period' => '2026-06-01',
            'opening_balance_bank' => 2000000,
            'closing_balance_bank' => 1500000,
            'opening_balance_book' => 2000000,
            'closing_balance_book' => 1500000,
            'status' => BankReconciliation::STATUS_COMPLETED,
            'started_by' => $this->user->id,
            'completed_at' => now(),
            'completed_by' => $this->user->id,
        ]);

        BankStatementLine::create([
            'bank_reconciliation_id' => $prior->id,
            'transaction_date' => '2026-06-20',
            'description' => 'Biaya belum cocok',
            'debit' => 50000,
            'credit' => 0,
            'matched_status' => BankStatementLine::MATCH_UNMATCHED,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/RK_JULI_2026.pdf'),
            'RK_JULI_2026.pdf',
            'application/pdf',
            null,
            true,
        );

        $july = app(ReconciliationService::class)->createFromPdf(
            $this->bankAccount->id,
            '2026-07-01',
            $file,
        );

        $this->assertNotNull($july->notes);
        $this->assertStringContainsString('Outstanding', $july->notes);
        $this->assertStringContainsString('Biaya belum cocok', $july->notes);
    }

    public function test_create_from_pdf_fixture(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/RK_JULI_2026.pdf'),
            'RK_JULI_2026.pdf',
            'application/pdf',
            null,
            true,
        );

        $reconciliation = app(ReconciliationService::class)->createFromPdf(
            $this->bankAccount->id,
            '2026-07-01',
            $file,
        );

        $this->assertSame(16881280.35, (float) $reconciliation->opening_balance_bank);
        $this->assertSame(2522380.35, (float) $reconciliation->closing_balance_bank);
        $this->assertCount(2, $reconciliation->bankLines);
    }

    private function createManualReconciliation(string $period): BankReconciliation
    {
        return BankReconciliation::create([
            'account_id' => $this->bankAccount->id,
            'period' => $period,
            'opening_balance_bank' => 0,
            'closing_balance_bank' => 0,
            'opening_balance_book' => 0,
            'closing_balance_book' => 0,
            'status' => BankReconciliation::STATUS_IN_REVIEW,
            'started_by' => $this->user->id,
        ]);
    }
}
