<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class ScopingSuggestionReply
{
    public function __construct(
        public ?ScopingSuggestion $suggestion,
        public AssistantUsage $usage,
    ) {}
}
