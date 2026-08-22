<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Program;
use App\Models\ProgramParticipant;
use DomainException;
use Illuminate\Support\Facades\DB;

class ProgramService
{
    public function generateCode(?string $date = null): string
    {
        $year = \Carbon\Carbon::parse($date ?? now())->format('Y');
        $prefix = 'PRG';

        $last = Program::where('code', 'like', "$prefix-$year-%")
            ->orderByDesc('code')
            ->value('code');

        $seq = $last ? (int) explode('-', $last)[2] + 1 : 1;

        return sprintf('%s-%s-%03d', $prefix, $year, $seq);
    }

    public function create(array $data): Program
    {
        $data['code'] = $this->generateCode($data['start_date'] ?? null);

        return Program::create($data);
    }

    public function update(Program $program, array $data): Program
    {
        $program->update($data);

        return $program->fresh();
    }

    public function addParticipant(Program $program, array $data): ProgramParticipant
    {
        return $program->participants()->create($data);
    }

    public function updateParticipant(ProgramParticipant $participant, array $data): ProgramParticipant
    {
        $participant->update($data);

        return $participant->fresh();
    }

    public function removeParticipant(ProgramParticipant $participant): void
    {
        $participant->delete();
    }

    public function destroy(Program $program): void
    {
        if ($program->transactions()->exists()) {
            throw new DomainException('Program tidak dapat dihapus karena masih memiliki transaksi terkait.');
        }

        DB::transaction(function () use ($program) {
            $program->participants()->delete();
            $program->delete();
        });
    }

    /** @return array{revenue: float, expense: float, profit: float, revenue_lines: array, expense_lines: array} */
    public function profitLoss(Program $program): array
    {
        $revenueAccountIds = Account::where('type', 'revenue')->pluck('id');
        $expenseAccountIds = Account::where('type', 'expense')->pluck('id');

        $baseQuery = JournalEntry::query()
            ->whereHas('transaction', fn ($q) => $q->where('program_id', $program->id));

        $revenueLines = (clone $baseQuery)
            ->whereIn('account_id', $revenueAccountIds)
            ->with('account')
            ->select('account_id', DB::raw('SUM(credit - debit) as net'))
            ->groupBy('account_id')
            ->get()
            ->map(fn ($e) => [
                'code' => $e->account->code,
                'name' => $e->account->name,
                'net' => round((float) $e->net, 2),
            ])
            ->filter(fn ($line) => $line['net'] != 0)
            ->values()
            ->all();

        $expenseLines = (clone $baseQuery)
            ->whereIn('account_id', $expenseAccountIds)
            ->with('account')
            ->select('account_id', DB::raw('SUM(credit - debit) as net'))
            ->groupBy('account_id')
            ->get()
            ->map(fn ($e) => [
                'code' => $e->account->code,
                'name' => $e->account->name,
                'net' => round(-(float) $e->net, 2),
            ])
            ->filter(fn ($line) => $line['net'] != 0)
            ->values()
            ->all();

        $revenue = round(array_sum(array_column($revenueLines, 'net')), 2);
        $expense = round(array_sum(array_column($expenseLines, 'net')), 2);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'profit' => round($revenue - $expense, 2),
            'revenue_lines' => $revenueLines,
            'expense_lines' => $expenseLines,
        ];
    }
}
