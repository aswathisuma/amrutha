<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = ['tags' => 'array', 'whatsapp' => 'boolean'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /** Lifetime spend across paid bookings. Eager-load `bookings` when listing. */
    public function getSpendAttribute(): int
    {
        return (int) $this->bookings
            ->whereIn('status', Booking::PAID_STATUSES)
            ->sum(fn ($b) => $b->final_amount ?? $b->est_amount);
    }

    public function getCompletedCountAttribute(): int
    {
        return $this->bookings->whereIn('status', Booking::PAID_STATUSES)->count();
    }
}
