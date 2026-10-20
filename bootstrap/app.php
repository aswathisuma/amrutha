<?php

use App\Exceptions\WorkflowException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\AssignRequestId::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'crm' => \App\Http\Middleware\EnsureCanSee::class,
            'idempotent' => \App\Http\Middleware\EnforceIdempotency::class,
        ]);
        $middleware->throttleApi('api');
        // Meta posts webhook events without a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhook/whatsapp']);
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson();

        // Consistent JSON error envelope for the API (spec §8.1). Never leaks stack traces.
        $envelope = function (Request $r, int $status, string $code, string $message, $details = null) {
            return response()->json(array_filter([
                'error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details], fn ($v) => $v !== null),
                'request_id' => $r->attributes->get('request_id'),
            ]), $status);
        };

        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $isApi($r));

        $exceptions->render(function (WorkflowException $e, Request $r) use ($isApi, $envelope) {
            if ($isApi($r)) {
                return $envelope($r, $e->status, $e->errorCode, $e->getMessage());
            }

            return back()->with('toast', [$e->getMessage(), 'err']);
        });

        $exceptions->render(function (\Throwable $e, Request $r) use ($isApi, $envelope) {
            if (! $isApi($r) || $e instanceof WorkflowException) {
                return null; // default handling
            }

            return match (true) {
                $e instanceof ValidationException => $envelope($r, 422, 'validation_failed', 'The given data was invalid.', $e->errors()),
                $e instanceof AuthenticationException => $envelope($r, 401, 'unauthenticated', 'Authentication required.'),
                $e instanceof AuthorizationException => $envelope($r, 403, 'forbidden', 'You are not allowed to do this.'),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => $envelope($r, 404, 'not_found', 'Resource not found.'),
                $e instanceof ThrottleRequestsException => $envelope($r, 429, 'rate_limited', 'Too many requests.'),
                $e instanceof HttpExceptionInterface => $envelope($r, $e->getStatusCode(), match ($e->getStatusCode()) {
                    403 => 'forbidden', 409 => 'conflict', 400 => 'bad_request', default => 'http_error',
                }, $e->getMessage() ?: 'Request failed.'),
                default => config('app.debug') ? null : $envelope($r, 500, 'server_error', 'Something went wrong.'),
            };
        });
    })->create();
