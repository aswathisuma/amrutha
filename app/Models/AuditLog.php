<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who changed what (spec §10 "Audit"). Sensitive keys are redacted before storage. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected const REDACT = ['password', 'remember_token', 'token', 'access_token', 'secret'];

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $entity = null, ?array $before = null, ?array $after = null): self
    {
        $request = request();

        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'before' => static::redact($before),
            'after' => static::redact($after),
            'request_id' => $request?->attributes->get('request_id'),
            'ip' => $request?->ip(),
        ]);
    }

    protected static function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        foreach ($data as $k => $v) {
            if (in_array($k, self::REDACT, true)) {
                $data[$k] = '[redacted]';
            }
        }

        return $data;
    }
}
