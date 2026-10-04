<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Exceptions;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use LogicException;

final class TransitionNotAllowed extends LogicException
{
    public static function between(ServiceJobStatus $from, ServiceJobStatus $to): self
    {
        return new self("A job cannot move from {$from->value} to {$to->value}.");
    }
}
