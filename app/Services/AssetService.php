<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetService
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function generateAssetNo(): string
    {
        $prefix = 'AST';
        $year = now()->format('Y');

        $last = Asset::where('asset_no', 'like', "$prefix-$year-%")
            ->orderByDesc('asset_no')
            ->value('asset_no');

        $seq = $last ? (int) explode('-', $last)[2] + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    public function create(array $data): Asset
    {
        $cost = (float) $data['cost'];
        $life = (int) $data['useful_life_months'];

        $data['asset_no'] = $this->generateAssetNo();
        $data['status'] = 'active';
        $data['accumulated_depreciation'] = 0;
        $data['monthly_depreciation'] = round($cost / $life, 2);
        $data['user_id'] = auth()->id();

        $fixedAssetAccount = $this->accountByCode('1200');
        $cashAccount = (int) $data['cash_account_id'];

        return DB::transaction(function () use ($data, $cost, $fixedAssetAccount, $cashAccount) {
            $journal = $this->transactions->create([
                'type' => 'journal',
                'date' => $data['acquisition_date'],
                'description' => 'Perolehan aset ' . $data['asset_no'] . ' - ' . $data['name'],
                'lines' => [
                    ['account_id' => $fixedAssetAccount, 'debit' => $cost, 'credit' => 0],
                    ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $cost],
                ],
            ]);

            $data['transaction_id'] = $journal->id;

            return Asset::create($data);
        });
    }

  /**
   * @return Collection<int, array{asset: Asset, amount: float}>
   */
    public function previewDepreciation(string $month): Collection
    {
        $period = $this->parseMonth($month);
        $periodKey = $period->format('Y-m');

        return Asset::query()
            ->where('status', 'active')
            ->whereDate('acquisition_date', '<=', $period->copy()->endOfMonth())
            ->orderBy('asset_no')
            ->get()
            ->map(function (Asset $asset) use ($period, $periodKey) {
                if ($asset->depreciations()->where('date', $period->copy()->endOfMonth()->toDateString())->exists()) {
                    return null;
                }

                $amount = $this->depreciationAmountForMonth($asset, $periodKey);
                if ($amount <= 0) {
                    return null;
                }

                return ['asset' => $asset, 'amount' => $amount];
            })
            ->filter()
            ->values();
    }

    public function postDepreciation(string $month): AssetDepreciation
    {
        $preview = $this->previewDepreciation($month);

        if ($preview->isEmpty()) {
            throw new DomainException('Tidak ada aset yang perlu disusutkan untuk periode ini.');
        }

        $period = $this->parseMonth($month);
        $depreciationDate = $period->copy()->endOfMonth()->toDateString();
        $total = round($preview->sum(fn ($row) => $row['amount']), 2);

        $expenseAccount = $this->accountByCode('5950');
        $accumAccount = $this->accountByCode('1201');

        return DB::transaction(function () use ($preview, $depreciationDate, $total, $expenseAccount, $accumAccount, $period) {
            $journal = $this->transactions->create([
                'type' => 'journal',
                'date' => $depreciationDate,
                'description' => 'Penyusutan aset ' . $period->format('M Y'),
                'lines' => [
                    ['account_id' => $expenseAccount, 'debit' => $total, 'credit' => 0],
                    ['account_id' => $accumAccount, 'debit' => 0, 'credit' => $total],
                ],
            ]);

            $firstDepreciation = null;

            foreach ($preview as $row) {
                $asset = $row['asset'];
                $amount = $row['amount'];

                $depreciation = $asset->depreciations()->create([
                    'date' => $depreciationDate,
                    'amount' => $amount,
                    'transaction_id' => $journal->id,
                ]);

                $firstDepreciation ??= $depreciation;

                $newAccum = round((float) $asset->accumulated_depreciation + $amount, 2);
                $asset->update([
                    'accumulated_depreciation' => $newAccum,
                    'status' => $newAccum >= (float) $asset->cost ? 'fully_depreciated' : 'active',
                ]);
            }

            return $firstDepreciation;
        });
    }

    public function delete(Asset $asset): void
    {
        if ($asset->depreciations()->exists()) {
            throw new DomainException('Aset sudah punya penyusutan. Hapus tidak diizinkan.');
        }

        DB::transaction(function () use ($asset) {
            if ($asset->transaction_id) {
                $asset->transaction?->journalEntries()->delete();
                $asset->transaction?->delete();
            }

            $asset->delete();
        });
    }

    private function depreciationAmountForMonth(Asset $asset, string $monthKey): float
    {
        $acquisitionKey = $asset->acquisition_date->format('Y-m');
        if ($monthKey < $acquisitionKey) {
            return 0;
        }

        $cost = (float) $asset->cost;
        $accumulated = (float) $asset->accumulated_depreciation;
        $remaining = round($cost - $accumulated, 2);

        if ($remaining <= 0) {
            return 0;
        }

        $monthly = (float) $asset->monthly_depreciation;
        $monthsPosted = $asset->depreciations()->count();
        $isLastMonth = ($monthsPosted + 1) >= (int) $asset->useful_life_months;

        if ($isLastMonth || $remaining <= $monthly) {
            return $remaining;
        }

        return $monthly;
    }

    private function parseMonth(string $month): Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new DomainException('Format periode tidak valid. Gunakan YYYY-MM.');
        }

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    }

    private function accountByCode(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }
}
