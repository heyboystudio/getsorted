<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use App\Domain\Assistant\Support\BookingToolbox;

/**
 * Everything Siya may see for one turn (spec 020): the scrubbed chat, the booking state through the toolbox the
 * model can change it with, and approved product facts. Never names, addresses, phone numbers, schedule or account IDs.
 */
final readonly class ChatRequest
{
    /**
     * @param  list<array{role: 'customer'|'assistant', text: string}>  $transcript  customer text already scrubbed; the last entry is the customer's new message
     * @param  list<string>  $productFacts
     */
    public function __construct(
        public BookingToolbox $toolbox,
        public array $transcript,
        public array $productFacts = [],
        public string $bookingStage = 'chat',
        /** Set on the single regeneration when the first reply broke a rule. */
        public ?string $guardFeedback = null,
    ) {}
}
