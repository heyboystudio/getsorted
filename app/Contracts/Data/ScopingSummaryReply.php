<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class ScopingSummaryReply
{
    public function __construct(
        public ?string $summary,
        public AssistantUsage $usage,
    ) {}
}
