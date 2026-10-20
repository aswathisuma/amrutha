<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/** File stored on a private disk; viewed only through short-lived signed URLs (spec §3). */
class Attachment extends Model
{
    protected $guarded = [];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function signedUrl(int $minutes = 10): string
    {
        return URL::temporarySignedRoute('attachments.show', now()->addMinutes($minutes), ['attachment' => $this->id]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
