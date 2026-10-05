<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/**
 * Everything Siya may see for one turn (spec 016): catalogue keys and names,
 * the confirmed service's questions, answers so far and the scrubbed chat.
 * Never names, addresses, phone numbers or account IDs.
 */
final readonly class ChatRequest
{
    /**
     * @param  array<string, array{name: string, services: array<string, string>}>  $catalogue  trade key => name and service key => name
     * @param  list<array{key: string, prompt: string, type: string, options: list<string>, required: bool}>  $questions
     * @param  array<string, mixed>  $answers  question key => raw answer
     * @param  list<array{role: 'customer'|'assistant', text: string}>  $transcript
     */
    public function __construct(
        public array $catalogue,
        public ?string $confirmedServiceKey,
        public array $questions,
        public array $answers,
        public array $transcript,
    ) {}
}
