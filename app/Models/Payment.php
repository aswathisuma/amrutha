<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment ledger entry. Rows are append-only: refunds and reversals are new
 * negative rows, never edits or deletes of the original (spec §4.3 step 22).
 */
class Payment extends Model
{
    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Payment ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Payment ledger entries cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
