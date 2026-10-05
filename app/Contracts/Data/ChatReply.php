<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/** One Siya turn. Every field is a suggestion the caller validates (spec 016, AC2, AC3, AC14). */
final readonly class ChatReply
{
    /** @param  array<string, mixed>  $answers  question key => raw answer the customer gave in their words */
    public function __construct(
        public ?string $reply,
        public ?string $tradeKey,
        public ?string $serviceKey,
        public array $answers,
        public AssistantUsage $usage,
    ) {}
}
