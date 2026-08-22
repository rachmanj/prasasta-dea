<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchGroupBookLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'match_group_id',
        'transaction_id',
    ];

    public function matchGroup(): BelongsTo
    {
        return $this->belongsTo(ReconciliationMatchGroup::class, 'match_group_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
