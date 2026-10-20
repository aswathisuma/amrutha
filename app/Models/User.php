<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'branch_id', 'technician_id', 'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function roleLabel(): string
    {
        return config("crm.roles.{$this->role}.label", ucfirst($this->role));
    }

    /** Whether this user may open the given navigation section. */
    public function canSee(string $section): bool
    {
        $nav = config("crm.roles.{$this->role}.nav", []);

        return $nav === '*' || in_array($section, $nav, true);
    }

    public function homeSection(): string
    {
        $nav = config("crm.roles.{$this->role}.nav", []);

        return $nav === '*' ? 'dashboard' : ($nav[0] ?? 'dashboard');
    }

    public function isBranchScoped(): bool
    {
        return $this->role === 'manager' && $this->branch_id;
    }

    /** Restrict a booking query to what this user is allowed to see. */
    public function scopeBookingQuery(Builder $q): Builder
    {
        if ($this->isBranchScoped()) {
            $q->where('branch_id', $this->branch_id);
        }
        if ($this->role === 'tech') {
            $q->where('technician_id', $this->technician_id);
        }

        return $q;
    }

    public function canAccessBooking(Booking $b): bool
    {
        if ($this->isBranchScoped()) {
            return $b->branch_id === $this->branch_id;
        }
        if ($this->role === 'tech') {
            return $b->technician_id === $this->technician_id;
        }

        return true;
    }
}
