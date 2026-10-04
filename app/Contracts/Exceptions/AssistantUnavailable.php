<?php

declare(strict_types=1);

namespace App\Contracts\Exceptions;

use RuntimeException;

/** The AI provider could not answer (timeout, outage, rate limit); callers fall back to the manual path. */
final class AssistantUnavailable extends RuntimeException
{
    public function __construct(public readonly bool $timedOut = false, ?\Throwable $previous = null)
    {
        parent::__construct($timedOut ? 'The assistant timed out.' : 'The assistant is unavailable.', 0, $previous);
    }
}
