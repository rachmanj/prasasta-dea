<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderBy('name')
            ->get();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contact::create($request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:student,vendor,instructor,donor,employee,other',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]));

        return back()->with('success', 'Kontak berhasil ditambahkan.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|in:student,vendor,instructor,donor,employee,other',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]));

        return back()->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        if ($contact->bills()->exists() || $contact->cashAdvances()->exists()) {
            return back()->with('error', 'Kontak tidak bisa dihapus karena punya tagihan atau kas bon.');
        }

        $contact->delete();

        return back()->with('success', 'Kontak berhasil dihapus.');
    }
}
