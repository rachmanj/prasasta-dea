<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\CashOpnameService;
use App\Services\ReportService;
use App\Services\TransactionService;
use App\Support\Terbilang;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashOpnameTest extends TestCase
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

    private function fundCash(float $amount, string $date = '2026-08-22'): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();

        app(TransactionService::class)->create([
            'type' => 'receipt',
            'date' => $date,
            'description' => 'Setoran kas',
            'account_id' => $cash->id,
            'category_id' => $revenue->id,
            'amount' => $amount,
        ]);
    }

  /** @return array<int, array{denomination: int, type: string, units: int}> */
    private function linesWithPhysicalTotal(float $target): array
    {
        $remaining = (int) round($target);
        $lines = [];

        foreach (CashOpnameService::DENOMINATIONS as $denom) {
            $value = $denom['denomination'];
            $units = intdiv($remaining, $value);
            $remaining -= $units * $value;
            $lines[] = [
                'denomination' => $value,
                'type' => $denom['type'],
                'units' => $units,
            ];
        }

        return $lines;
    }

    public function test_create_balanced_opname(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(1_000_000),
        ]);

        $this->assertSame('open', $opname->status);
        $this->assertEquals(1_000_000, (float) $opname->book_balance);
        $this->assertEquals(1_000_000, (float) $opname->physical_balance);
        $this->assertEquals(0, (float) $opname->difference);
        $this->assertSame('Cocok', $opname->status_badge['label']);
    }

    public function test_create_shortage_opname(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(800_000),
        ]);

        $this->assertEquals(200_000, (float) $opname->difference);
        $this->assertSame('Selisih Kurang', $opname->status_badge['label']);
    }

    public function test_create_surplus_opname(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(1_200_000),
        ]);

        $this->assertEquals(-200_000, (float) $opname->difference);
        $this->assertSame('Selisih Lebih', $opname->status_badge['label']);
    }

    public function test_posting_shortage_creates_balanced_journal(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(800_000),
        ]);

        $adjusted = app(CashOpnameService::class)->adjust($opname);

        $this->assertSame('adjusted', $adjusted->status);
        $entries = $adjusted->adjustmentTransaction->journalEntries;
        $this->assertEquals(200_000, (float) $entries->sum('debit'));
        $this->assertEquals(200_000, (float) $entries->sum('credit'));

        $expense = Account::where('code', '5700')->first();
        $cash = Account::where('code', '1000')->first();

        $this->assertEquals(200_000, (float) $entries->where('account_id', $expense->id)->sum('debit'));
        $this->assertEquals(200_000, (float) $entries->where('account_id', $cash->id)->sum('credit'));
        $this->assertStringStartsWith('JU-', $adjusted->adjustmentTransaction->journal_no);
    }

    public function test_posting_surplus_creates_balanced_journal(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(1_200_000),
        ]);

        $adjusted = app(CashOpnameService::class)->adjust($opname);

        $this->assertSame('adjusted', $adjusted->status);
        $entries = $adjusted->adjustmentTransaction->journalEntries;
        $this->assertEquals(200_000, (float) $entries->sum('debit'));
        $this->assertEquals(200_000, (float) $entries->sum('credit'));

        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4600')->first();

        $this->assertEquals(200_000, (float) $entries->where('account_id', $cash->id)->sum('debit'));
        $this->assertEquals(200_000, (float) $entries->where('account_id', $revenue->id)->sum('credit'));
    }

    public function test_guard_posting_when_difference_zero(): void
    {
        $this->fundCash(500_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(500_000),
        ]);

        $this->expectException(DomainException::class);
        app(CashOpnameService::class)->adjust($opname);
    }

    public function test_guard_double_posting_rejected(): void
    {
        $this->fundCash(1_000_000);

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(900_000),
        ]);

        app(CashOpnameService::class)->adjust($opname);

        $this->expectException(DomainException::class);
        app(CashOpnameService::class)->adjust($opname->fresh());
    }

    public function test_sequential_opname_numbering(): void
    {
        $this->fundCash(100_000);

        $first = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(100_000),
        ]);

        $second = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(100_000),
        ]);

        $this->assertSame('OPN-2026-0001', $first->number);
        $this->assertSame('OPN-2026-0002', $second->number);
    }

    public function test_pengurus_cannot_create_or_adjust_but_can_view(): void
    {
        $this->fundCash(500_000);

        $pengurus = User::factory()->create();
        $pengurus->assignRole('pengurus');

        $opname = app(CashOpnameService::class)->create([
            'date' => '2026-08-22',
            'lines' => $this->linesWithPhysicalTotal(400_000),
        ]);

        $this->actingAs($pengurus)
            ->get(route('cash-opnames.index'))
            ->assertOk();

        $this->actingAs($pengurus)
            ->get(route('cash-opnames.show', $opname))
            ->assertOk();

        $this->actingAs($pengurus)
            ->get(route('cash-opnames.create'))
            ->assertForbidden();

        $this->actingAs($pengurus)
            ->post(route('cash-opnames.store'), [
                'date' => '2026-08-22',
                'lines' => $this->linesWithPhysicalTotal(400_000),
            ])
            ->assertForbidden();

        $this->actingAs($pengurus)
            ->post(route('cash-opnames.adjust', $opname))
            ->assertForbidden();
    }

    public function test_terbilang_for_millions(): void
    {
        $this->assertSame(
            'Lima Belas Juta Seratus Delapan Puluh Tiga Ribu Lima Ratus Rupiah',
            Terbilang::rupiah(15_183_500),
        );
        $this->assertSame('Nol Rupiah', Terbilang::rupiah(0));
    }
}
