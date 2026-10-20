<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule rejected the request (invalid status jump, scheduling
 * conflict, overpayment …). Rendered as 409/422 JSON for the API and as an
 * error toast for the web UI.
 */
class WorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'conflict', public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message, string $code = 'validation_failed'): self
    {
        return new self($message, $code, 422);
    }
}
