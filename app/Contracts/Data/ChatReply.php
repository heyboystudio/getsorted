<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/** One Siya turn: the reply text. State changes were already made, and validated, through the toolbox (spec 020). */
final readonly class ChatReply
{
    public function __construct(
        public ?string $reply,
        public AssistantUsage $usage,
        public int $steps = 1,
        public int $toolCalls = 0,
    ) {}
}
