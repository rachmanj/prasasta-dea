<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'journal_no',
        'type',
        'date',
        'description',
        'ref_no',
        'status',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function matchGroupBookLines(): HasMany
    {
        return $this->hasMany(MatchGroupBookLine::class);
    }
}
