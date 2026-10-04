<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

/** A pro's application and standing (spec 008). Only ProStatusMachine changes it. */
enum ProStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    /** Set by the pro in a later spec (journey P4); never eligible. */
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('In progress'),
            self::Submitted => __('Under review'),
            self::ChangesRequested => __('Changes requested'),
            self::Approved => __('Approved'),
            self::Rejected => __('Not approved'),
            self::Suspended => __('Suspended'),
            self::Paused => __('Paused'),
        };
    }

    /** The pro may change their application. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }
}
