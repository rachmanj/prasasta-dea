<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashOpname extends Model
{
    protected $fillable = [
        'number',
        'date',
        'account_id',
        'book_balance',
        'physical_balance',
        'difference',
        'status',
        'adjustment_transaction_id',
        'prepared_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'book_balance' => 'decimal:2',
        'physical_balance' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    protected $appends = ['status_badge'];

    public function lines(): HasMany
    {
        return $this->hasMany(CashOpnameLine::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        if ($this->status === 'adjusted') {
            return ['label' => 'Disesuaikan', 'color' => 'blue'];
        }

        $diff = (float) $this->difference;

        if ($diff == 0) {
            return ['label' => 'Cocok', 'color' => 'green'];
        }

        if ($diff > 0) {
            return ['label' => 'Selisih Kurang', 'color' => 'orange'];
        }

        return ['label' => 'Selisih Lebih', 'color' => 'red'];
    }
}
