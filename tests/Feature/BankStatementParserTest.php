<?php

namespace Tests\Feature;

use App\Services\BankStatementParser;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankStatementParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_mandiri_kopra_july_2026_fixture(): void
    {
        $this->seed(AccountSeeder::class);

        $result = app(BankStatementParser::class)->parse(
            base_path('tests/fixtures/RK_JULI_2026.pdf'),
        );

        $this->assertSame(16881280.35, $result['opening_balance']);
        $this->assertSame(2522380.35, $result['closing_balance']);
        $this->assertSame('2026-07-01', $result['period_start']);
        $this->assertSame('2026-07-31', $result['period_end']);
        $this->assertCount(2, $result['lines']);

        $tarik = $result['lines'][0];
        $this->assertSame('2026-07-06', $tarik['transaction_date']);
        $this->assertStringContainsString('Tarik Tunai', $tarik['description']);
        $this->assertSame(14333900.0, $tarik['debit']);
        $this->assertSame(0.0, $tarik['credit']);
        $this->assertSame(2547380.35, $tarik['balance']);

        $adm = $result['lines'][1];
        $this->assertSame('2026-07-31', $adm['transaction_date']);
        $this->assertStringContainsString('Biaya Adm', $adm['description']);
        $this->assertSame(25000.0, $adm['debit']);
        $this->assertSame(0.0, $adm['credit']);
        $this->assertSame(2522380.35, $adm['balance']);
    }
}
