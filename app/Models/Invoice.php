<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = ['issued_at' => 'datetime', 'paid_at' => 'datetime'];

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

    public function isPaid(): bool
    {
        return $this->status === 'Paid';
    }

    public function statusColor(): string
    {
        return $this->isPaid() ? 'bg-brand-50 text-brand-600' : 'bg-orange-50 text-orange-700';
    }

    public static function nextNumber(): string
    {
        return 'INV-'.(24000 + (int) static::max('id'));
    }
}
