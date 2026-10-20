<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const PAID_STATUSES = ['Paid', 'Closed'];

    public const BILLED_STATUSES = ['Invoice Generated', 'Payment Pending', 'Paid', 'Closed'];

    protected $guarded = [];

    protected $casts = ['scheduled_on' => 'date'];

    protected static function booted(): void
    {
        // Assign a human-readable reference once the row has an id.
        static::created(function (Booking $b) {
            if (! $b->ref) {
                $b->ref = 'HF-'.(240000 + $b->id);
                $b->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function nextStatus(): ?string
    {
        $statuses = config('crm.statuses');
        $i = array_search($this->status, $statuses, true);

        return $i !== false && $i < count($statuses) - 1 ? $statuses[$i + 1] : null;
    }

    public function amount(): int
    {
        return $this->final_amount ?? $this->est_amount;
    }
}
