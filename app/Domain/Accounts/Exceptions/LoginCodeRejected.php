<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

/** A login code that cannot be used. `reason` tells the screen which message to show. */
final class LoginCodeRejected extends RuntimeException
{
    public const string INCORRECT = 'incorrect';

    public const string EXPIRED = 'expired';

    public function __construct(
        public readonly string $reason,
        public readonly ?int $attemptsLeft = null,
    ) {
        parent::__construct("Login code rejected: {$reason}.");
    }
}
