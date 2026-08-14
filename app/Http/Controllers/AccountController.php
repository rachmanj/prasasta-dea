<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Accounts/Index', [
            'accounts' => Account::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Account::create($this->validated($request));

        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request, $account->id));

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->journalEntries()->exists()) {
            return back()->with('error', 'Akun tidak bisa dihapus karena sudah dipakai di jurnal.');
        }

        $account->delete();

        return back()->with('success', 'Akun berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => 'required|string|max:20|unique:accounts,code' . ($ignoreId ? ',' . $ignoreId : ''),
            'name' => 'required|string|max:191',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'is_bank' => 'boolean',
            'bank_name' => 'nullable|string|max:191',
            'account_number' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);
    }
}
