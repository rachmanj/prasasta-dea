<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(): Response
    {
        $assets = Asset::query()
            ->orderByDesc('acquisition_date')
            ->paginate(20);

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Assets/Form', [
            'cashAccounts' => Account::where('is_bank', true)
                ->orWhere('code', '1000')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(Request $request, AssetService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'cost' => 'required|numeric|min:0.01',
            'acquisition_date' => 'required|date',
            'useful_life_months' => 'required|integer|min:1',
            'cash_account_id' => 'required|exists:accounts,id',
        ]);

        $service->create($data);

        return redirect()->route('assets.index')
            ->with('success', 'Aset berhasil didaftarkan.');
    }

    public function show(Asset $asset): Response
    {
        $asset->load([
            'transaction.journalEntries.account',
            'depreciations.transaction.journalEntries.account',
        ]);

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
        ]);
    }

    public function destroy(Asset $asset, AssetService $service): RedirectResponse
    {
        try {
            $service->delete($asset);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.index')
            ->with('success', 'Aset berhasil dihapus.');
    }

    public function depreciation(Request $request, AssetService $service): Response
    {
        $month = $request->input('month', now()->format('Y-m'));

        $preview = [];
        if ($request->has('month')) {
            $preview = $service->previewDepreciation($month)
                ->map(fn ($row) => [
                    'id' => $row['asset']->id,
                    'asset_no' => $row['asset']->asset_no,
                    'name' => $row['asset']->name,
                    'amount' => $row['amount'],
                ])
                ->all();
        }

        return Inertia::render('Assets/Depreciation', [
            'month' => $month,
            'preview' => $preview,
            'generated' => $request->has('month'),
        ]);
    }

    public function postDepreciation(Request $request, AssetService $service): RedirectResponse
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        try {
            $service->postDepreciation($data['month']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.depreciation', ['month' => $data['month']])
            ->with('success', 'Penyusutan berhasil diposting.');
    }
}
