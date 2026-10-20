<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support for API writes (spec §8.1): the first response for a
 * key is stored and replayed for retries; reusing a key with a different body
 * is a 409.
 */
class EnforceIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '' || ! $request->isMethod('post')) {
            return $next($request);
        }
        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            return $this->error($request, 'invalid_idempotency_key', 'Idempotency-Key must be 8–128 URL-safe characters.', 400);
        }

        $userId = $request->user()?->id;
        $route = $request->method().' '.$request->path();
        $hash = hash('sha256', $request->getContent());

        $claimed = IdempotencyKey::query()->insertOrIgnore([
            'key' => $key, 'user_id' => $userId, 'route' => $route, 'request_hash' => $hash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = IdempotencyKey::where(['key' => $key, 'user_id' => $userId, 'route' => $route])->first();

        if (! $claimed) {
            if ($record->request_hash !== $hash) {
                return $this->error($request, 'idempotency_key_reused', 'This Idempotency-Key was already used with a different request.', 409);
            }
            if ($record->response_status === null) {
                return $this->error($request, 'request_in_progress', 'A request with this Idempotency-Key is still being processed.', 409);
            }

            return response($record->response_body, $record->response_status, [
                'Content-Type' => 'application/json',
                'Idempotent-Replayed' => 'true',
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            $record->delete(); // let the client retry server errors
        } else {
            $record->update(['response_status' => $response->getStatusCode(), 'response_body' => $response->getContent()]);
        }

        return $response;
    }

    protected function error(Request $request, string $code, string $message, int $status): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }
}
