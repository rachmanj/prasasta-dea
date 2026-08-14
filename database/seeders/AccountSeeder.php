<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Aset
            ['code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'is_bank' => false],
            ['code' => '1011', 'name' => 'Bank BCA', 'type' => 'asset', 'is_bank' => true, 'bank_name' => 'BCA'],
            ['code' => '1012', 'name' => 'Bank BRI', 'type' => 'asset', 'is_bank' => true, 'bank_name' => 'BRI'],
            ['code' => '1100', 'name' => 'Piutang Usaha', 'type' => 'asset', 'is_bank' => false],
            // Kewajiban
            ['code' => '2100', 'name' => 'Hutang Usaha', 'type' => 'liability', 'is_bank' => false],
            // Ekuitas
            ['code' => '3000', 'name' => 'Ekuitas Yayasan', 'type' => 'equity', 'is_bank' => false],
            ['code' => '3100', 'name' => 'Saldo Awal / Laba Ditahan', 'type' => 'equity', 'is_bank' => false],
            // Pendapatan
            ['code' => '4100', 'name' => 'Pendapatan Kursus / Pelatihan', 'type' => 'revenue', 'is_bank' => false],
            ['code' => '4200', 'name' => 'Pendapatan Donasi', 'type' => 'revenue', 'is_bank' => false],
            ['code' => '4300', 'name' => 'Pendapatan Hibah / Bantuan', 'type' => 'revenue', 'is_bank' => false],
            // Beban
            ['code' => '5100', 'name' => 'Beban Honor Pengajar', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5200', 'name' => 'Beban Gaji Staf', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5300', 'name' => 'Beban Sewa', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5400', 'name' => 'Beban Listrik / Air / Internet', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5500', 'name' => 'Beban ATK & Konsumsi', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5600', 'name' => 'Beban Pemeliharaan', 'type' => 'expense', 'is_bank' => false],
            ['code' => '5700', 'name' => 'Beban Operasional Lainnya', 'type' => 'expense', 'is_bank' => false],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(['code' => $account['code']], $account);
        }
    }
}
