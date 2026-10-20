<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_date' => 'date',
        'last_reminder_at' => 'datetime',
        'customer_snapshot' => 'array',
        'tax_rate' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isPaid(): bool
    {
        return $this->status === 'Paid';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'Paid' => 'bg-brand-50 text-brand-600',
            'Partial' => 'bg-amber-50 text-amber',
            'Refunded' => 'bg-slate-100 text-slate-500',
            default => 'bg-orange-50 text-orange-700',
        };
    }

    /** Name/phone/area as they were when the invoice was issued (falls back to the live record). */
    public function billedTo(): array
    {
        return $this->customer_snapshot ?? [
            'name' => $this->customer?->name,
            'phone' => $this->customer?->phone,
            'area' => $this->customer?->area,
        ];
    }

    public static function numberFor(int $id): string
    {
        return 'INV-'.(24000 + $id);
    }
}
