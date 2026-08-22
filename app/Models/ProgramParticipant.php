<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramParticipant extends Model
{
    protected $fillable = [
        'program_id',
        'name',
        'nis',
        'region',
        'fee',
        'paid_amount',
        'payment_date',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    protected $appends = ['status'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            $paid = (float) $this->paid_amount;
            $fee = (float) $this->fee;

            if ($paid <= 0) {
                return 'unpaid';
            }

            if ($paid >= $fee) {
                return 'paid';
            }

            return 'partial';
        });
    }
}
