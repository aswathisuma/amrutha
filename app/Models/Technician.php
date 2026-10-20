<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Technician extends Model
{
    protected $guarded = [];

    protected $casts = ['rating' => 'float'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'Available' => 'bg-brand-50 text-brand-600',
            'On Job' => 'bg-amber-50 text-amber',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
