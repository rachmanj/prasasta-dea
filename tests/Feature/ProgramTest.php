<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\TransactionService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, AccountSeeder::class]);

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole('bendahara');
        $this->actingAs($this->bendahara);
    }

    public function test_create_program_generates_sequential_code(): void
    {
        $service = app(ProgramService::class);

        $first = $service->create([
            'name' => 'MAB-BMC Batch I',
            'type' => 'group',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $second = $service->create([
            'name' => 'MAB-BMC Batch II',
            'type' => 'group',
            'start_date' => '2026-06-01',
            'status' => 'active',
        ]);

        $this->assertSame('PRG-2026-001', $first->code);
        $this->assertSame('PRG-2026-002', $second->code);
    }

    public function test_tagged_transactions_calculate_profit_loss_correctly(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch Test',
            'type' => 'group',
            'status' => 'active',
        ]);

        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();
        $expense = Account::where('code', '5700')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-01',
            'description' => 'Pembayaran kursus',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => 5_000_000,
            'program_id' => $program->id,
        ]);

        app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-08-05',
            'description' => 'Biaya operasional',
            'account_id' => $cash->id,
            'category_id' => $expense->id,
            'amount' => 1_500_000,
            'program_id' => $program->id,
        ]);

        $pl = app(ProgramService::class)->profitLoss($program);

        $this->assertEquals(5_000_000, $pl['revenue']);
        $this->assertEquals(1_500_000, $pl['expense']);
        $this->assertEquals(3_500_000, $pl['profit']);
    }

    public function test_profit_loss_separates_revenue_and_expense_lines(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch P&L Lines',
            'type' => 'group',
            'status' => 'active',
        ]);

        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();
        $expenseOps = Account::where('code', '5700')->first();
        $expenseCert = Account::where('code', '5810')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-01',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => 10_000_000,
            'program_id' => $program->id,
        ]);

        app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-08-02',
            'account_id' => $cash->id,
            'category_id' => $expenseOps->id,
            'amount' => 2_000_000,
            'program_id' => $program->id,
        ]);

        app(TransactionService::class)->create([
            'type' => 'payment',
            'date' => '2026-08-03',
            'account_id' => $cash->id,
            'category_id' => $expenseCert->id,
            'amount' => 500_000,
            'program_id' => $program->id,
        ]);

        $pl = app(ProgramService::class)->profitLoss($program);

        $this->assertCount(1, $pl['revenue_lines']);
        $this->assertEquals('4100', $pl['revenue_lines'][0]['code']);
        $this->assertEquals(10_000_000, $pl['revenue_lines'][0]['net']);

        $this->assertCount(2, $pl['expense_lines']);
        $expenseCodes = array_column($pl['expense_lines'], 'code');
        $this->assertContains('5700', $expenseCodes);
        $this->assertContains('5810', $expenseCodes);
    }

    public function test_participant_payment_status_accessor(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch Peserta',
            'type' => 'group',
            'status' => 'active',
        ]);

        $service = app(ProgramService::class);

        $unpaid = $service->addParticipant($program, [
            'name' => 'Siswa A',
            'fee' => 1_000_000,
            'paid_amount' => 0,
        ]);

        $partial = $service->addParticipant($program, [
            'name' => 'Siswa B',
            'fee' => 1_000_000,
            'paid_amount' => 500_000,
        ]);

        $paid = $service->addParticipant($program, [
            'name' => 'Siswa C',
            'fee' => 1_000_000,
            'paid_amount' => 1_000_000,
        ]);

        $this->assertSame('unpaid', $unpaid->status);
        $this->assertSame('partial', $partial->status);
        $this->assertSame('paid', $paid->status);
    }

    public function test_cannot_delete_program_with_tagged_transactions(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch Guard',
            'type' => 'group',
            'status' => 'active',
        ]);

        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => '2026-08-01',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => 100_000,
            'program_id' => $program->id,
        ]);

        $this->expectException(DomainException::class);
        app(ProgramService::class)->destroy($program);
    }

    public function test_can_delete_program_without_transactions(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch Kosong',
            'type' => 'group',
            'status' => 'active',
        ]);

        app(ProgramService::class)->addParticipant($program, [
            'name' => 'Siswa',
            'fee' => 500_000,
        ]);

        app(ProgramService::class)->destroy($program);

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
        $this->assertDatabaseMissing('program_participants', ['program_id' => $program->id]);
    }

    public function test_pengurus_read_only(): void
    {
        $program = app(ProgramService::class)->create([
            'name' => 'Batch Readonly',
            'type' => 'group',
            'status' => 'active',
        ]);

        $pengurus = User::factory()->create();
        $pengurus->assignRole('pengurus');

        $this->actingAs($pengurus)
            ->get(route('programs.index'))
            ->assertOk();

        $this->actingAs($pengurus)
            ->get(route('programs.show', $program))
            ->assertOk();

        $this->actingAs($pengurus)
            ->get(route('programs.create'))
            ->assertForbidden();

        $this->actingAs($pengurus)
            ->post(route('programs.store'), [
                'name' => 'Baru',
                'type' => 'group',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($pengurus)
            ->post(route('programs.participants.store', $program), [
                'name' => 'Siswa',
                'fee' => 100_000,
            ])
            ->assertForbidden();
    }
}
