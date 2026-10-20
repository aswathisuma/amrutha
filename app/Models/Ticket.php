<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $guarded = [];

    protected $casts = ['resolved_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'High' => 'bg-rose-50 text-rose-600',
            'Medium' => 'bg-amber-50 text-amber',
            default => 'bg-slate-100 text-slate-500',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'Resolved', 'Closed' => 'bg-brand-50 text-brand-600',
            'Open' => 'bg-rose-50 text-rose-600',
            default => 'bg-blue-50 text-blue-700',
        };
    }

    public static function numberFor(int $id): string
    {
        return 'TK-'.(500 + $id);
    }
}
