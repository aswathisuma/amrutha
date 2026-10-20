<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public static function nextNumber(): string
    {
        return 'TK-'.(500 + (int) static::max('id'));
    }
}
