<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use App\Domain\Assistant\Enums\ConversationIntent;

/** One Siya turn. Every field is a suggestion the caller validates (spec 016, AC2, AC3, AC14). */
final readonly class ChatReply
{
    /** @param  array<string, mixed>  $answers  question key => raw answer the customer gave in their words
     * @param  list<string>|null  $jobNotes  exact excerpts of customer-provided job facts, excluding superseded facts */
    public function __construct(
        public ?string $reply,
        public ?string $tradeKey,
        public ?string $serviceKey,
        public array $answers,
        public AssistantUsage $usage,
        public ?ConversationIntent $intent = ConversationIntent::HomeProblem,
        public ?string $questionKey = null,
        public ?array $jobNotes = null,
        public bool $readyToBook = false,
    ) {}
}
