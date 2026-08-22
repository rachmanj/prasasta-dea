<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOpnameLine extends Model
{
    protected $fillable = [
        'cash_opname_id',
        'denomination',
        'type',
        'units',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashOpname(): BelongsTo
    {
        return $this->belongsTo(CashOpname::class);
    }
}
