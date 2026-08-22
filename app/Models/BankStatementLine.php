<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BankStatementLine extends Model
{
    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_MATCHED = 'matched';

    public const MATCH_MANUAL = 'manual';

    public const MATCH_EXCLUDED = 'excluded';

    protected $fillable = [
        'bank_reconciliation_id',
        'transaction_date',
        'description',
        'reference',
        'debit',
        'credit',
        'balance',
        'matched_status',
        'exclude_reason',
        'line_order',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            ReconciliationMatchGroup::class,
            'match_group_bank_lines',
            'bank_statement_line_id',
            'match_group_id',
        );
    }

    public function netAmount(): float
    {
        return round((float) $this->debit - (float) $this->credit, 2);
    }

    public function isAvailableForMatching(): bool
    {
        return $this->matched_status === self::MATCH_UNMATCHED;
    }
}
