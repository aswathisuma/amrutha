<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only record of every booking status change (spec §5). */
class BookingStatusHistory extends Model
{
    protected $table = 'booking_status_history';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorName(): string
    {
        return $this->actor?->name ?? $this->actor_label ?? 'System';
    }
}
