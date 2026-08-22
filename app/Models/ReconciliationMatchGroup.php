<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationMatchGroup extends Model
{
    public const TYPE_AUTO = 'auto';

    public const TYPE_MANUAL = 'manual';

    protected $fillable = [
        'bank_reconciliation_id',
        'match_type',
        'bank_total',
        'book_total',
        'difference',
        'created_by',
    ];

    protected $casts = [
        'bank_total' => 'decimal:2',
        'book_total' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bankLinePivots(): HasMany
    {
        return $this->hasMany(MatchGroupBankLine::class, 'match_group_id');
    }

    public function bookLinePivots(): HasMany
    {
        return $this->hasMany(MatchGroupBookLine::class, 'match_group_id');
    }

    public function bankLines(): BelongsToMany
    {
        return $this->belongsToMany(
            BankStatementLine::class,
            'match_group_bank_lines',
            'match_group_id',
            'bank_statement_line_id',
        );
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(
            Transaction::class,
            'match_group_book_lines',
            'match_group_id',
            'transaction_id',
        );
    }
}
