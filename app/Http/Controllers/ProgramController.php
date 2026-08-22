<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\ProgramParticipant;
use App\Services\ProgramService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(ReportService $reports): Response
    {
        return Inertia::render('Programs/Index', [
            'programs' => $reports->programSummaries(),
            'canManage' => auth()->user()?->hasAnyRole(['admin', 'bendahara']) ?? false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Programs/Form', [
            'program' => null,
        ]);
    }

    public function store(Request $request, ProgramService $service): RedirectResponse
    {
        $data = $this->validatedProgram($request);

        $program = $service->create($data);

        return redirect()->route('programs.show', $program)
            ->with('success', 'Program berhasil dibuat.');
    }

    public function show(Program $program, ProgramService $service): Response
    {
        $program->load('participants');

        return Inertia::render('Programs/Show', [
            'program' => $program,
            'profitLoss' => $service->profitLoss($program),
            'canManage' => auth()->user()?->hasAnyRole(['admin', 'bendahara']) ?? false,
        ]);
    }

    public function edit(Program $program): Response
    {
        return Inertia::render('Programs/Form', [
            'program' => $program,
        ]);
    }

    public function update(Request $request, Program $program, ProgramService $service): RedirectResponse
    {
        $data = $this->validatedProgram($request);

        $service->update($program, $data);

        return redirect()->route('programs.show', $program)
            ->with('success', 'Program berhasil diperbarui.');
    }

    public function destroy(Program $program, ProgramService $service): RedirectResponse
    {
        try {
            $service->destroy($program);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('programs.index')
            ->with('success', 'Program berhasil dihapus.');
    }

    public function storeParticipant(Request $request, Program $program, ProgramService $service): RedirectResponse
    {
        $data = $this->validatedParticipant($request);

        $service->addParticipant($program, $data);

        return back()->with('success', 'Peserta berhasil ditambahkan.');
    }

    public function updateParticipant(Request $request, ProgramParticipant $participant, ProgramService $service): RedirectResponse
    {
        $data = $this->validatedParticipant($request);

        $service->updateParticipant($participant, $data);

        return back()->with('success', 'Peserta berhasil diperbarui.');
    }

    public function destroyParticipant(ProgramParticipant $participant, ProgramService $service): RedirectResponse
    {
        $service->removeParticipant($participant);

        return back()->with('success', 'Peserta berhasil dihapus.');
    }

    private function validatedProgram(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:group,individual',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:active,completed,cancelled',
            'notes' => 'nullable|string',
        ]);
    }

    private function validatedParticipant(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'nis' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:50',
            'fee' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
        ]);
    }
}
