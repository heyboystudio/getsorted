<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

use App\Contracts\Data\MessageChannel;
use Carbon\CarbonImmutable;

final readonly class SentLoginCodeData
{
    /**
     * @param  string|null  $developmentCode  only set in local development (spec 001, AC20)
     */
    public function __construct(
        public string $phoneE164,
        public MessageChannel $channel,
        public CarbonImmutable $sentAt,
        public ?string $developmentCode,
    ) {}
}
