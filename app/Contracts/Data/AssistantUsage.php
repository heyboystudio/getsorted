<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/** Which model answered and what it cost in tokens; never contains customer text. */
final readonly class AssistantUsage
{
    public function __construct(
        public string $provider,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
