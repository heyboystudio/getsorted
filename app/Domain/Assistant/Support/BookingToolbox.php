<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

use App\Domain\Assistant\State\BookingState;
use Illuminate\Support\Str;

/**
 * The only way Siya's model changes the booking state (spec 020). Each method validates one proposal,
 * applies it when it is sound and always returns a structured result the model reads, so a rejected
 * item is explained to the model instead of failing the whole turn. Tool writes are idempotent.
 */
final class BookingToolbox
{
    public const int MAX_FACT_LENGTH = 140;

    /** The booking steps Siya may offer once a trade and a problem are known. */
    public const array STEPS = ['sign_in', 'location', 'when', 'photos', 'review'];

    public int $calls = 0;

    public int $rejected = 0;

    /** @var list<string> scrubbed customer messages the evidence quotes are checked against */
    private array $customerText;

    /**
     * @param  array<string, string>  $trades  active trade key => name
     * @param  list<array{role: string, text: string}>  $transcript  already scrubbed
     */
    public function __construct(public readonly BookingState $state, private readonly array $trades, array $transcript)
    {
        $this->customerText = array_values(array_map(
            fn (array $turn): string => self::normalise($turn['text']),
            array_filter($transcript, fn (array $turn): bool => $turn['role'] === 'customer'),
        ));
    }

    /** @return array<string, mixed> */
    public function digest(): array
    {
        return [
            'trade' => $this->state->tradeKey === null ? null : ['key' => $this->state->tradeKey, 'name' => $this->trades[$this->state->tradeKey] ?? $this->state->tradeKey],
            'facts' => array_map(fn (array $fact): array => ['id' => $fact['id'], 'text' => $fact['text']], $this->state->facts),
            'urgent' => $this->state->urgent,
            'parked_jobs' => $this->state->parked,
            'still_needed' => $this->state->missing(),
            'ready_for_next_step' => $this->state->isReady(),
            'available_trades' => $this->trades,
        ];
    }

    /** @return array<string, mixed> */
    public function setTrade(string $tradeKey): array
    {
        $this->calls++;
        $tradeKey = trim($tradeKey);

        if (! array_key_exists($tradeKey, $this->trades)) {
            return $this->reject('Unknown trade key.', ['allowed_trade_keys' => array_keys($this->trades)]);
        }

        $changed = $this->state->tradeKey !== $tradeKey;
        $this->state->tradeKey = $tradeKey;

        return ['ok' => true, 'trade' => $this->trades[$tradeKey], 'changed' => $changed, 'facts_kept' => count($this->state->facts)];
    }

    /** @return array<string, mixed> */
    public function addFact(string $text, string $evidence): array
    {
        $this->calls++;
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '' || mb_strlen($text) > self::MAX_FACT_LENGTH) {
            return $this->reject('A fact is a short phrase of at most '.self::MAX_FACT_LENGTH.' characters.');
        }

        if (! SummaryRules::acceptable($text)) {
            return $this->reject('A fact cannot contain prices, contact details or addresses.');
        }

        $needle = self::normalise($evidence);

        if (mb_strlen($needle) < 3 || ! $this->quoted($needle)) {
            return $this->reject('The evidence must be an exact quote from something the customer wrote.');
        }

        foreach ($this->state->facts as $fact) {
            if (self::normalise($fact['text']) === self::normalise($text)) {
                return ['ok' => true, 'id' => $fact['id'], 'duplicate' => true];
            }
        }

        if (count($this->state->facts) >= BookingState::MAX_FACTS) {
            return $this->reject('Too many facts already; remove or merge one first.');
        }

        $id = 'f'.Str::lower(Str::random(6));
        $this->state->facts[] = ['id' => $id, 'text' => $text, 'turn' => $this->state->turn];

        return ['ok' => true, 'id' => $id];
    }

    /** @return array<string, mixed> */
    public function removeFact(string $id): array
    {
        $this->calls++;
        $before = count($this->state->facts);
        $this->state->facts = array_values(array_filter($this->state->facts, fn (array $fact): bool => $fact['id'] !== trim($id)));

        return $before === count($this->state->facts)
            ? $this->reject('No fact with that id.', ['facts' => $this->digest()['facts']])
            : ['ok' => true];
    }

    /** @return array<string, mixed> */
    public function setUrgency(bool $urgent): array
    {
        $this->calls++;
        $this->state->urgent = $urgent;

        return ['ok' => true, 'urgent' => $urgent];
    }

    /** @return array<string, mixed> */
    public function parkJob(string $summary): array
    {
        $this->calls++;
        $summary = trim((string) preg_replace('/\s+/u', ' ', $summary));

        if ($summary === '' || mb_strlen($summary) > self::MAX_FACT_LENGTH || ! SummaryRules::acceptable($summary)) {
            return $this->reject('Describe the other job in a short phrase without prices or contact details.');
        }

        if (count($this->state->parked) < 3 && ! in_array($summary, $this->state->parked, true)) {
            $this->state->parked[] = $summary;
        }

        return ['ok' => true, 'parked_jobs' => $this->state->parked];
    }

    /** @return array<string, mixed> */
    public function offerNextStep(string $step): array
    {
        $this->calls++;

        if (! in_array($step, self::STEPS, true)) {
            return $this->reject('Unknown step.', ['allowed_steps' => self::STEPS]);
        }

        if (! $this->state->isReady()) {
            return $this->reject('Not ready: the booking still needs something.', ['still_needed' => $this->state->missing()]);
        }

        $this->state->nextStepOffered = true;

        return ['ok' => true, 'note' => 'The app now shows the customer the secure control for this step. Do not describe it as a form.'];
    }

    private function quoted(string $needle): bool
    {
        foreach ($this->customerText as $text) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function reject(string $reason, array $extra = []): array
    {
        $this->rejected++;

        return ['ok' => false, 'error' => $reason, ...$extra];
    }

    /** Lowercase, without punctuation and with single spaces, so quotes match despite formatting. */
    private static function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($text))));
    }
}
