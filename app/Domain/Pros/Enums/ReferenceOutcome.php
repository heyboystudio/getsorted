<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

enum ReferenceOutcome: string
{
    case Pending = 'pending';
    case Positive = 'positive';
    case Negative = 'negative';
    case NoAnswer = 'no_answer';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Not contacted yet'),
            self::Positive => __('Contacted: positive'),
            self::Negative => __('Contacted: negative'),
            self::NoAnswer => __('No answer'),
        };
    }

    /** A pro may replace this reference when changes are requested. */
    public function needsReplacing(): bool
    {
        return in_array($this, [self::Negative, self::NoAnswer], true);
    }
}
