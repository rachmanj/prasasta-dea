<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $bendahara;

    protected User $pengurus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, AccountSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->bendahara = User::factory()->create();
        $this->bendahara->assignRole('bendahara');

        $this->pengurus = User::factory()->create();
        $this->pengurus->assignRole('pengurus');
    }

    public function test_admin_can_access_users_and_opening_balances(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('opening-balances.index'))
            ->assertOk();
    }

    public function test_bendahara_cannot_access_admin_only_routes_but_can_access_transactions_and_reports(): void
    {
        $this->actingAs($this->bendahara)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($this->bendahara)
            ->get(route('opening-balances.index'))
            ->assertForbidden();

        $this->actingAs($this->bendahara)
            ->get(route('transactions.index'))
            ->assertOk();

        $this->actingAs($this->bendahara)
            ->get(route('reports.cash-flow'))
            ->assertOk();
    }

    public function test_pengurus_is_read_only(): void
    {
        $cash = Account::where('code', '1000')->first();
        $revenue = Account::where('code', '4100')->first();

        $this->actingAs($this->pengurus)
            ->post(route('transactions.store'), [
                'type' => 'receipt',
                'date' => '2026-08-14',
                'description' => 'Test',
                'account_id' => $cash->id,
                'category_id' => $revenue->id,
                'amount' => 100000,
            ])
            ->assertForbidden();

        $this->actingAs($this->pengurus)
            ->post(route('accounts.store'), [
                'code' => '9999',
                'name' => 'Test Account',
                'type' => 'expense',
            ])
            ->assertForbidden();

        $this->actingAs($this->pengurus)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($this->pengurus)
            ->get(route('reports.profit-loss'))
            ->assertOk();
    }

    public function test_bills_create_page_is_reachable_not_shadowed_by_show_route(): void
    {
        $this->actingAs($this->admin)
            ->get(route('bills.create', ['type' => 'payable']))
            ->assertOk();

        $this->actingAs($this->bendahara)
            ->get(route('bills.create', ['type' => 'receivable']))
            ->assertOk();

        $this->actingAs($this->pengurus)
            ->get(route('bills.create', ['type' => 'payable']))
            ->assertForbidden();
    }
}
