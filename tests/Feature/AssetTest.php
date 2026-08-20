<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetService;
use App\Services\ReportService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('bendahara');
        $this->actingAs($user);
    }

    public function test_create_posts_balanced_journal_dr_1200_cr_kas(): void
    {
        $cash = Account::where('code', '1000')->first();

        $asset = app(AssetService::class)->create([
            'name' => 'Laptop',
            'cost' => 1200000,
            'acquisition_date' => '2026-08-01',
            'useful_life_months' => 12,
            'cash_account_id' => $cash->id,
        ]);

        $this->assertSame('active', $asset->status);
        $this->assertSame('AST', explode('-', $asset->asset_no)[0]);
        $this->assertEquals(100000, (float) $asset->monthly_depreciation);

        $entries = $asset->transaction->journalEntries;
        $this->assertEquals(1200000, (float) $entries->sum('debit'));
        $this->assertEquals(1200000, (float) $entries->sum('credit'));

        $fixedAsset = Account::where('code', '1200')->first();
        $this->assertEquals(1200000, (float) $entries->where('account_id', $fixedAsset->id)->sum('debit'));
        $this->assertEquals(1200000, (float) $entries->where('account_id', $cash->id)->sum('credit'));
    }

    public function test_preview_depreciation_returns_correct_monthly_amount(): void
    {
        $cash = Account::where('code', '1000')->first();
        $service = app(AssetService::class);

        $service->create([
            'name' => 'Meja',
            'cost' => 1200000,
            'acquisition_date' => '2026-08-15',
            'useful_life_months' => 12,
            'cash_account_id' => $cash->id,
        ]);

        $preview = $service->previewDepreciation('2026-08');
        $this->assertCount(1, $preview);
        $this->assertEquals(100000, $preview->first()['amount']);

        $empty = $service->previewDepreciation('2026-07');
        $this->assertCount(0, $empty);
    }

    public function test_post_depreciation_posts_balanced_journal_and_updates_accumulated(): void
    {
        $cash = Account::where('code', '1000')->first();
        $service = app(AssetService::class);

        $service->create([
            'name' => 'Printer',
            'cost' => 1200000,
            'acquisition_date' => '2026-08-01',
            'useful_life_months' => 12,
            'cash_account_id' => $cash->id,
        ]);

        $service->postDepreciation('2026-08');

        $asset = Asset::first();
        $this->assertEquals(100000, (float) $asset->accumulated_depreciation);
        $this->assertSame('active', $asset->status);

        $depreciation = $asset->depreciations()->first();
        $entries = $depreciation->transaction->journalEntries;
        $this->assertEquals(100000, (float) $entries->sum('debit'));
        $this->assertEquals(100000, (float) $entries->sum('credit'));

        $expense = Account::where('code', '5950')->first();
        $accum = Account::where('code', '1201')->first();
        $this->assertEquals(100000, (float) $entries->where('account_id', $expense->id)->sum('debit'));
        $this->assertEquals(100000, (float) $entries->where('account_id', $accum->id)->sum('credit'));
    }

    public function test_stops_at_fully_depreciated_with_last_month_rounding(): void
    {
        $cash = Account::where('code', '1000')->first();
        $service = app(AssetService::class);

        $service->create([
            'name' => 'Kursi',
            'cost' => 1000,
            'acquisition_date' => '2026-01-01',
            'useful_life_months' => 3,
            'cash_account_id' => $cash->id,
        ]);

        $service->postDepreciation('2026-01');
        $service->postDepreciation('2026-02');
        $service->postDepreciation('2026-03');

        $asset = Asset::first();
        $this->assertEquals(1000, (float) $asset->accumulated_depreciation);
        $this->assertSame('fully_depreciated', $asset->status);
        $this->assertEquals(0, $asset->book_value);

        $amounts = $asset->depreciations()->orderBy('date')->pluck('amount')->map(fn ($v) => (float) $v)->all();
        $this->assertEquals([333.33, 333.33, 333.34], $amounts);

        $preview = $service->previewDepreciation('2026-04');
        $this->assertCount(0, $preview);
    }

    public function test_delete_blocked_when_depreciations_exist(): void
    {
        $cash = Account::where('code', '1000')->first();
        $service = app(AssetService::class);

        $asset = $service->create([
            'name' => 'Scanner',
            'cost' => 500000,
            'acquisition_date' => '2026-08-01',
            'useful_life_months' => 10,
            'cash_account_id' => $cash->id,
        ]);

        $service->postDepreciation('2026-08');

        $this->expectException(DomainException::class);
        $service->delete($asset);
    }

    public function test_delete_allowed_without_depreciations(): void
    {
        $cash = Account::where('code', '1000')->first();
        $service = app(AssetService::class);

        $asset = $service->create([
            'name' => 'Proyektor',
            'cost' => 500000,
            'acquisition_date' => '2026-08-01',
            'useful_life_months' => 10,
            'cash_account_id' => $cash->id,
        ]);

        $service->delete($asset);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }
}
