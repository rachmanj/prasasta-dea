<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'account_id',
        'period',
        'opening_balance_bank',
        'closing_balance_bank',
        'opening_balance_book',
        'closing_balance_book',
        'status',
        'started_by',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected $casts = [
        'period' => 'date',
        'opening_balance_bank' => 'decimal:2',
        'closing_balance_bank' => 'decimal:2',
        'opening_balance_book' => 'decimal:2',
        'closing_balance_book' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function bankLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function matchGroups(): HasMany
    {
        return $this->hasMany(ReconciliationMatchGroup::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isEditable(): bool
    {
        return ! $this->isCompleted();
    }
}
