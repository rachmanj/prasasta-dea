<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\BillService;
use App\Services\ReportService;
use App\Services\TransactionService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
    }

    public function test_receipt_posts_balanced_double_entry(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();

        $tx = app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-14',
            'description' => 'Test',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => 1000000,
        ]);

        $this->assertEquals(1000000, (float) $tx->journalEntries->sum('debit'));
        $this->assertEquals(1000000, (float) $tx->journalEntries->sum('credit'));
        $this->assertSame('BKM', explode('-', $tx->journal_no)[0]);

        // cash balance reflects the debit
        $balance = app(ReportService::class)->accountBalance($cash->id);
        $this->assertEquals(1000000, $balance);
    }

    public function test_payment_posts_balanced_double_entry(): void
    {
        $cash = Account::where('code', '1000')->first();
        $expense = Account::where('code', '5100')->first();

        $tx = app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-08-14',
            'description' => 'Honor',
            'account_id' => $cash->id,
            'category_id' => $expense->id,
            'amount' => 300000,
        ]);

        $this->assertEquals(300000, (float) $tx->journalEntries->sum('debit'));
        $this->assertEquals(300000, (float) $tx->journalEntries->sum('credit'));
        $this->assertSame('BKK', explode('-', $tx->journal_no)[0]);

        $balance = app(ReportService::class)->accountBalance($cash->id);
        $this->assertEquals(-300000, $balance);
    }

    public function test_receivable_bill_accrual_and_payment(): void
    {
        $contact = Contact::create(['name' => 'Siswa A', 'type' => 'student']);
        $revenue = Account::where('code', '4100')->first();
        $cash = Account::where('code', '1000')->first();

        $bill = app(BillService::class)->create([
            'type' => 'receivable',
            'contact_id' => $contact->id,
            'description' => 'Biaya kursus',
            'amount' => 500000,
            'date' => '2026-08-14',
            'due_date' => '2026-09-14',
            'account_id' => $revenue->id,
        ]);

        $this->assertSame('open', $bill->status);
        $this->assertSame('INV', explode('-', $bill->bill_no)[0]);

        // accrual: revenue recognized, piutang booked
        $this->assertEquals(500000, app(ReportService::class)->accountBalance(
            Account::where('code', '1100')->first()->id
        ));

        app(BillService::class)->recordPayment($bill, [
            'amount' => 500000,
            'account_id' => $cash->id,
            'date' => '2026-08-14',
        ]);

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEquals(500000, (float) $bill->paid_amount);

        // after full payment, piutang cleared and cash received
        $this->assertEquals(0, app(ReportService::class)->accountBalance(
            Account::where('code', '1100')->first()->id
        ));
        $this->assertEquals(500000, app(ReportService::class)->accountBalance($cash->id));
    }

    public function test_opening_balance_creates_balanced_journal(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $cash = Account::where('code', '1000')->first();
        $retained = Account::where('code', '3100')->first();

        $this->actingAs($admin)->post(route('opening-balances.store'), [
            'balances' => [
                $cash->id => 5000000,
            ],
            'date' => '2026-01-01',
        ])->assertRedirect();

        $this->assertEquals(5000000, app(ReportService::class)->accountBalance($cash->id));

        $retainedCredit = JournalEntry::where('account_id', $retained->id)->sum('credit');
        $this->assertEquals(5000000, (float) $retainedCredit);
    }

    public function test_cash_flow_includes_cash_on_hand_account(): void
    {
        $cash = Account::where('code', '1000')->first();
        $loan = Account::create(['code' => '2200', 'name' => 'Hutang Pinjaman', 'type' => 'liability', 'is_bank' => false]);

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-05',
            'description' => 'Dana pinjaman (Soft Loan) dari ARKA',
            'account_id' => $cash->id,
            'category_id' => $loan->id,
            'amount' => 20000000,
        ]);

        $flow = app(ReportService::class)->cashFlow('2026-08-01', '2026-08-31');

        $this->assertEquals(20000000, $flow['total_inflow']);
        $this->assertEquals(0, $flow['total_outflow']);
        $this->assertArrayHasKey('2200 - Hutang Pinjaman', $flow['inflow_by_category']);
    }

    public function test_profit_loss_signs_and_cash_flow_no_double_count(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();
        $expense = Account::where('code', '5100')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt', 'date' => '2026-01-10', 'description' => 'Pendapatan',
            'account_id' => $cash->id, 'category_id' => $revenue->id, 'amount' => 1000000,
        ]);
        app(TransactionService::class)->create([
            'type' => 'payment', 'date' => '2026-01-11', 'description' => 'Honor',
            'account_id' => $cash->id, 'category_id' => $expense->id, 'amount' => 300000,
        ]);

        $pl = app(ReportService::class)->profitLoss('2026-01-01', '2026-01-31');
        $this->assertEquals(1000000, $pl['total_revenue']);
        $this->assertEquals(300000, $pl['total_expense']); // positive, not negative
        $this->assertEquals(700000, $pl['profit']); // revenue - expense

        $cf = app(ReportService::class)->cashFlow('2026-01-01', '2026-01-31');
        $this->assertEquals(1000000, $cf['total_inflow']);
        $this->assertEquals(300000, $cf['total_outflow']);
        $this->assertEquals(700000, $cf['net']);
    }
}
