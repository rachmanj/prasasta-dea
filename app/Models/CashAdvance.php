<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvance extends Model
{
    protected $fillable = [
        'advance_no',
        'contact_id',
        'amount',
        'date',
        'description',
        'status',
        'realized_amount',
        'returned_amount',
        'transaction_id',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'realized_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(CashAdvanceRealization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingAttribute(): float
    {
        return round((float) $this->amount - (float) $this->realized_amount - (float) $this->returned_amount, 2);
    }
}
