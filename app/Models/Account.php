<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_bank',
        'bank_name',
        'account_number',
        'is_active',
    ];

    protected $casts = [
        'is_bank' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
