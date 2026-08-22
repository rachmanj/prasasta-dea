<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchGroupBankLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'match_group_id',
        'bank_statement_line_id',
    ];

    public function matchGroup(): BelongsTo
    {
        return $this->belongsTo(ReconciliationMatchGroup::class, 'match_group_id');
    }

    public function bankStatementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class);
    }
}
