<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    // Delivery statuses only move forward; webhooks can arrive out of order (spec §9).
    public const STATUS_RANK = ['queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime', 'status_at' => 'datetime', 'is_template' => 'boolean'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** Apply a provider status unless it would move the message backwards. */
    public function applyStatus(string $status, ?string $errorCode = null, ?string $errorMessage = null): bool
    {
        if ($status !== 'failed' && (self::STATUS_RANK[$status] ?? 0) <= (self::STATUS_RANK[$this->status] ?? 0)) {
            return false;
        }
        $this->update([
            'status' => $status,
            'status_at' => now(),
            'error_code' => $status === 'failed' ? $errorCode : $this->error_code,
            'error_message' => $status === 'failed' ? $errorMessage : $this->error_message,
        ]);

        return true;
    }

    public function statusIcon(): string
    {
        return match ($this->status) {
            'queued' => '🕓',
            'sent' => '✓',
            'delivered' => '✓✓',
            'read' => '✓✓ read',
            'failed' => '⚠ failed',
            'local' => '○ not sent (local mode)',
            default => '',
        };
    }
}
