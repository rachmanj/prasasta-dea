<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'asset_no',
        'name',
        'cost',
        'acquisition_date',
        'useful_life_months',
        'monthly_depreciation',
        'accumulated_depreciation',
        'status',
        'transaction_id',
        'user_id',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'monthly_depreciation' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'acquisition_date' => 'date',
    ];

    protected $appends = ['book_value'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getBookValueAttribute(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }
}
