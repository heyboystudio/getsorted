<?php

declare(strict_types=1);

namespace App\Domain\Assistant\State;

/**
 * What Siya's conversation has established so far (spec 020). The application owns it; the model
 * only changes it through BookingToolbox. It holds no personal data: no names, addresses or contacts.
 */
final class BookingState
{
    public const int MAX_FACTS = 12;

    /**
     * @param  list<array{id: string, text: string, turn: int}>  $facts  short facts from the customer's own words
     * @param  list<string>  $parked  other, unrelated jobs mentioned; each needs its own booking
     */
    public function __construct(
        public ?string $tradeKey = null,
        public array $facts = [],
        public bool $urgent = false,
        public array $parked = [],
        public int $turn = 0,
        public bool $nextStepOffered = false,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            is_string($data['tradeKey'] ?? null) ? $data['tradeKey'] : null,
            array_values(array_filter((array) ($data['facts'] ?? []), fn (mixed $fact): bool => is_array($fact) && is_string($fact['id'] ?? null) && is_string($fact['text'] ?? null))),
            (bool) ($data['urgent'] ?? false),
            array_values(array_filter((array) ($data['parked'] ?? []), is_string(...))),
            (int) ($data['turn'] ?? 0),
            (bool) ($data['nextStepOffered'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tradeKey' => $this->tradeKey, 'facts' => $this->facts, 'urgent' => $this->urgent,
            'parked' => $this->parked, 'turn' => $this->turn, 'nextStepOffered' => $this->nextStepOffered,
        ];
    }

    /** @return list<string> */
    public function factTexts(): array
    {
        return array_map(fn (array $fact): string => $fact['text'], $this->facts);
    }

    /**
     * What a pro needs before this can be booked: a trade and at least one concrete problem fact.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(array_filter([
            $this->tradeKey === null ? 'trade' : null,
            $this->facts === [] ? 'problem' : null,
        ]));
    }

    public function isReady(): bool
    {
        return $this->missing() === [];
    }
}
