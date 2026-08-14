<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\BillPayment;
use DomainException;
use Illuminate\Support\Facades\DB;

class BillService
{
    public function __construct(private readonly TransactionService $transactions) {}

    public function generateBillNo(string $type): string
    {
        $prefix = $type === 'receivable' ? 'INV' : 'BLL';
        $year = now()->format('Y');

        $last = Bill::where('bill_no', 'like', "$prefix-$year-%")
            ->orderByDesc('bill_no')
            ->value('bill_no');

        $seq = $last ? (int) explode('-', $last)[2] + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    public function create(array $data): Bill
    {
        $data['bill_no'] = $this->generateBillNo($data['type']);
        $data['status'] = 'open';
        $data['paid_amount'] = 0;

        return DB::transaction(function () use ($data) {
            $journal = $this->transactions->create([
                'type' => 'journal',
                'date' => $data['date'],
                'description' => 'Tagihan ' . $data['bill_no'],
                'lines' => $this->accrualLines($data),
            ]);

            $data['transaction_id'] = $journal->id;

            return Bill::create($data);
        });
    }

    public function recordPayment(Bill $bill, array $data): BillPayment
    {
        $amount = (float) $data['amount'];
        $accountId = $data['account_id'];
        $date = $data['date'];

        if ($amount <= 0 || $amount > $bill->remaining) {
            throw new DomainException('Nominal pembayaran melebihi sisa tagihan.');
        }

        return DB::transaction(function () use ($bill, $amount, $accountId, $date) {
            $isReceivable = $bill->type === 'receivable';

            $tx = $this->transactions->create([
                'type' => $isReceivable ? 'receipt' : 'payment',
                'date' => $date,
                'description' => 'Pembayaran ' . ($isReceivable ? 'piutang' : 'hutang') . ' ' . $bill->bill_no,
                'account_id' => $accountId,
                'category_id' => $this->controlAccount($bill->type),
                'amount' => $amount,
            ]);

            $payment = $bill->payments()->create([
                'transaction_id' => $tx->id,
                'amount' => $amount,
                'date' => $date,
            ]);

            $this->refreshStatus($bill);

            return $payment;
        });
    }

    public function delete(Bill $bill): void
    {
        if ($bill->payments()->exists()) {
            throw new DomainException('Tagihan sudah punya pembayaran. Hapus dulu transaksi pembayarannya.');
        }

        DB::transaction(function () use ($bill) {
            if ($bill->transaction_id) {
                $bill->transaction?->journalEntries()->delete();
                $bill->transaction?->delete();
            }

            $bill->delete();
        });
    }

    private function accrualLines(array $data): array
    {
        $amount = (float) $data['amount'];

        if ($data['type'] === 'receivable') {
            return [
                ['account_id' => $this->controlAccount('receivable'), 'debit' => $amount, 'credit' => 0],
                ['account_id' => $data['account_id'], 'debit' => 0, 'credit' => $amount],
            ];
        }

        return [
            ['account_id' => $data['account_id'], 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->controlAccount('payable'), 'debit' => 0, 'credit' => $amount],
        ];
    }

    private function controlAccount(string $type): int
    {
        $code = $type === 'receivable' ? '1100' : '2100';

        return Account::where('code', $code)->firstOrFail()->id;
    }

    private function refreshStatus(Bill $bill): void
    {
        $paid = (float) $bill->payments()->sum('amount');

        $status = 'open';
        if ($paid >= (float) $bill->amount) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        $bill->update(['paid_amount' => $paid, 'status' => $status]);
    }
}
