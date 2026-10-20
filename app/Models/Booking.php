<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    public const PAID_STATUSES = ['Paid', 'Closed'];

    public const BILLED_STATUSES = ['Invoice Generated', 'Payment Pending', 'Paid', 'Closed'];

    public const DONE_STATUSES = ['Service Completed', 'Invoice Generated', 'Payment Pending', 'Paid', 'Closed'];

    protected $guarded = [];

    protected $casts = [
        'scheduled_on' => 'date',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'charges_approved_at' => 'datetime',
    ];

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

    public function history(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function chargesApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charges_approved_by');
    }

    /** Statuses this booking may move to next, per the state machine. */
    public function allowedTransitions(): array
    {
        return config('crm.transitions')[$this->status] ?? [];
    }

    /** The next step on the happy path (first non-exception transition), if any. */
    public function nextStatus(): ?string
    {
        foreach ($this->allowedTransitions() as $s) {
            if (! in_array($s, config('crm.exception_statuses'), true)) {
                return $s;
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, config('crm.active_statuses'), true);
    }

    public function amount(): int
    {
        return $this->final_amount ?? $this->est_amount;
    }

    /** Service + materials − discount, before tax. */
    public function subtotal(): int
    {
        return max(0, $this->amount() + (int) $this->material_charge - (int) $this->discount);
    }

    /** Whether the final charges differ from the estimate by more than the approval threshold. */
    public function chargesNeedApproval(): bool
    {
        $est = max(1, (int) $this->est_amount);

        return abs($this->subtotal() - $est) / $est > config('crm.charge_approval_threshold');
    }
}
