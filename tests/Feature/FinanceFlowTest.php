<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Services\BillService;
use App\Services\ReportService;
use App\Services\TransactionService;
use Database\Seeders\AccountSeeder;
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
}
