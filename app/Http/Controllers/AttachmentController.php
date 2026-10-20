<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Streams a private attachment. Requires a valid signed URL *and* access to the parent record. */
class AttachmentController extends Controller
{
    public function __invoke(Request $request, Attachment $attachment)
    {
        $parent = $attachment->attachable;
        if ($parent instanceof Booking) {
            abort_unless($request->user()->canAccessBooking($parent), 403);
        }

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], $attachment->isImage() ? 'inline' : 'attachment');
    }
}
