<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashAdvance;
use App\Models\Contact;
use App\Services\CashAdvanceService;
use App\Services\ReportService;
use Database\Seeders\AccountSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private Contact $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->employee = Contact::create(['name' => 'Budi Karyawan', 'type' => 'employee']);
    }

    public function test_create_advance_posts_balanced_journal_dr_1150_cr_kas(): void
    {
        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'description' => 'Belanja ATK',
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        $this->assertSame('open', $advance->status);
        $this->assertSame('ADV', explode('-', $advance->advance_no)[0]);

        $entries = $advance->transaction->journalEntries;
        $this->assertEquals(500000, (float) $entries->sum('debit'));
        $this->assertEquals(500000, (float) $entries->sum('credit'));

        $advanceAccount = Account::where('code', '1150')->first();
        $cash = Account::where('code', '1000')->first();

        $this->assertEquals(500000, (float) $entries->where('account_id', $advanceAccount->id)->sum('debit'));
        $this->assertEquals(500000, (float) $entries->where('account_id', $cash->id)->sum('credit'));

        $this->assertEquals(500000, app(ReportService::class)->accountBalance($advanceAccount->id));
        $this->assertEquals(-500000, app(ReportService::class)->accountBalance($cash->id));
    }

    public function test_realize_posts_dr_beban_cr_1150_and_status_partial(): void
    {
        $expense = Account::where('code', '5500')->first();

        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        app(CashAdvanceService::class)->realize($advance, [
            'date' => '2026-08-21',
            'lines' => [
                ['account_id' => $expense->id, 'amount' => 300000, 'description' => 'Nota ATK'],
            ],
            'returned_amount' => 0,
        ]);

        $advance->refresh();
        $this->assertSame('partial', $advance->status);
        $this->assertEquals(300000, (float) $advance->realized_amount);
        $this->assertEquals(200000, $advance->remaining);

        $realization = $advance->realizations()->first();
        $entries = $realization->transaction->journalEntries;
        $this->assertEquals(300000, (float) $entries->sum('debit'));
        $this->assertEquals(300000, (float) $entries->sum('credit'));

        $advanceAccount = Account::where('code', '1150')->first();
        $this->assertEquals(300000, (float) $entries->where('account_id', $expense->id)->sum('debit'));
        $this->assertEquals(300000, (float) $entries->where('account_id', $advanceAccount->id)->sum('credit'));
    }

    public function test_return_posts_dr_kas_cr_1150_and_status_settled(): void
    {
        $expense = Account::where('code', '5500')->first();

        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        app(CashAdvanceService::class)->realize($advance, [
            'date' => '2026-08-21',
            'lines' => [
                ['account_id' => $expense->id, 'amount' => 300000],
            ],
            'returned_amount' => 200000,
        ]);

        $advance->refresh();
        $this->assertSame('settled', $advance->status);
        $this->assertEquals(300000, (float) $advance->realized_amount);
        $this->assertEquals(200000, (float) $advance->returned_amount);
        $this->assertEquals(0, $advance->remaining);

        $realization = $advance->realizations()->first();
        $entries = $realization->transaction->journalEntries;
        $cash = Account::where('code', '1000')->first();
        $advanceAccount = Account::where('code', '1150')->first();

        $this->assertEquals(200000, (float) $entries->where('account_id', $cash->id)->sum('debit'));
        $this->assertEquals(500000, (float) $entries->where('account_id', $advanceAccount->id)->sum('credit'));
    }

    public function test_overspend_reimburses_excess_to_cash(): void
    {
        $expense = Account::where('code', '5500')->first();

        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        app(CashAdvanceService::class)->realize($advance, [
            'date' => '2026-08-21',
            'lines' => [
                ['account_id' => $expense->id, 'amount' => 600000],
            ],
            'returned_amount' => 0,
        ]);

        $advance->refresh();
        $this->assertSame('settled', $advance->status);

        $entries = $advance->realizations()->first()->transaction->journalEntries;
        $cash = Account::where('code', '1000')->first();
        $advanceAccount = Account::where('code', '1150')->first();

        $this->assertEquals(500000, (float) $entries->where('account_id', $advanceAccount->id)->sum('credit'));
        $this->assertEquals(100000, (float) $entries->where('account_id', $cash->id)->sum('credit'));
    }

    public function test_delete_blocked_when_realizations_exist(): void
    {
        $expense = Account::where('code', '5500')->first();

        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        app(CashAdvanceService::class)->realize($advance, [
            'date' => '2026-08-21',
            'lines' => [
                ['account_id' => $expense->id, 'amount' => 100000],
            ],
            'returned_amount' => 0,
        ]);

        $this->expectException(DomainException::class);
        app(CashAdvanceService::class)->delete($advance);
    }

    public function test_delete_allowed_without_realizations(): void
    {
        $advance = app(CashAdvanceService::class)->create([
            'contact_id' => $this->employee->id,
            'amount' => 500000,
            'date' => '2026-08-20',
        ]);

        app(CashAdvanceService::class)->delete($advance);

        $this->assertDatabaseMissing('cash_advances', ['id' => $advance->id]);
    }
}
