<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    public function __construct(
        private BankStatementParser $parser,
        private ReportService $reports,
        private ReconciliationBalanceService $balanceService,
    ) {}

    public function createFromPdf(
        int $accountId,
        string $periodMonth,
        UploadedFile $file,
    ): BankReconciliation {
        $period = Carbon::parse($periodMonth)->startOfMonth();

        $exists = BankReconciliation::query()
            ->where('account_id', $accountId)
            ->where('period', $period->toDateString())
            ->exists();

        if ($exists) {
            throw new \DomainException('Rekonsiliasi untuk rekening dan periode ini sudah ada.');
        }

        $parsed = $this->parser->parse($file->getRealPath());

        $openingBook = $this->reports->accountBalance(
            $accountId,
            $period->copy()->subDay()->toDateString(),
        );
        $closingBook = $this->reports->accountBalance(
            $accountId,
            $period->copy()->endOfMonth()->toDateString(),
        );

        $carryForwardNote = $this->buildCarryForwardNote($accountId, $period);

        return DB::transaction(function () use ($accountId, $period, $parsed, $openingBook, $closingBook, $carryForwardNote) {
            $reconciliation = BankReconciliation::create([
                'account_id' => $accountId,
                'period' => $period->toDateString(),
                'opening_balance_bank' => $parsed['opening_balance'],
                'closing_balance_bank' => $parsed['closing_balance'],
                'opening_balance_book' => $openingBook,
                'closing_balance_book' => $closingBook,
                'status' => BankReconciliation::STATUS_IN_REVIEW,
                'started_by' => Auth::id(),
                'notes' => $carryForwardNote,
            ]);

            $order = 0;
            foreach ($parsed['lines'] as $line) {
                BankStatementLine::create([
                    'bank_reconciliation_id' => $reconciliation->id,
                    'transaction_date' => $line['transaction_date'],
                    'description' => $line['description'],
                    'reference' => $line['reference'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'balance' => $line['balance'],
                    'line_order' => $order++,
                ]);
            }

            return $reconciliation;
        });
    }

    public function excludeLine(BankReconciliation $reconciliation, BankStatementLine $line, ?string $reason): void
    {
        if (! $reconciliation->isEditable()) {
            throw new \DomainException('Sesi rekonsiliasi sudah selesai.');
        }

        if ((int) $line->bank_reconciliation_id !== (int) $reconciliation->id) {
            throw new \DomainException('Baris tidak termasuk sesi ini.');
        }

        if ($line->matched_status === BankStatementLine::MATCH_EXCLUDED) {
            $line->update(['matched_status' => BankStatementLine::MATCH_UNMATCHED, 'exclude_reason' => null]);

            return;
        }

        if ($line->matched_status !== BankStatementLine::MATCH_UNMATCHED) {
            throw new \DomainException('Hanya baris belum cocok yang dapat dikecualikan.');
        }

        $line->update([
            'matched_status' => BankStatementLine::MATCH_EXCLUDED,
            'exclude_reason' => $reason ?: 'Dikecualikan',
        ]);
    }

    public function complete(BankReconciliation $reconciliation): void
    {
        if (! $reconciliation->isEditable()) {
            throw new \DomainException('Sesi rekonsiliasi sudah selesai.');
        }

        if (! $this->balanceService->isBalanced($reconciliation)) {
            throw new \DomainException(
                'Rekonsiliasi belum balance. Pastikan semua baris cocok dan selisih unexplained ≈ 0.'
            );
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => Auth::id(),
        ]);
    }

    public function destroy(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->isCompleted()) {
            throw new \DomainException('Rekonsiliasi yang sudah selesai tidak dapat dihapus.');
        }

        $reconciliation->delete();
    }

    private function buildCarryForwardNote(int $accountId, Carbon $period): ?string
    {
        $prior = BankReconciliation::query()
            ->where('account_id', $accountId)
            ->where('period', '<', $period->toDateString())
            ->orderByDesc('period')
            ->first();

        if (! $prior) {
            return null;
        }

        $outstandingBank = BankStatementLine::query()
            ->where('bank_reconciliation_id', $prior->id)
            ->whereIn('matched_status', [
                BankStatementLine::MATCH_UNMATCHED,
                BankStatementLine::MATCH_EXCLUDED,
            ])
            ->get();

        if ($outstandingBank->isEmpty()) {
            return null;
        }

        $lines = $outstandingBank->map(function (BankStatementLine $line) {
            $status = $line->matched_status === BankStatementLine::MATCH_EXCLUDED ? 'dikecualikan' : 'belum cocok';

            return sprintf(
                '%s · %s · %s (%s)',
                $line->transaction_date->format('d M Y'),
                $line->description,
                number_format($line->netAmount(), 2, ',', '.'),
                $status,
            );
        })->implode('; ');

        return 'Outstanding dari '.Carbon::parse($prior->period)->format('M Y').': '.$lines;
    }
}
