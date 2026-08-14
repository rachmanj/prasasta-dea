<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'account_id',
        'date',
        'description',
        'amount',
        'source_ref',
        'is_matched',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_matched' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
