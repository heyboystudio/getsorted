<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/**
 * The assistant's guess at what the customer needs. It is only a suggestion:
 * callers must validate the keys against the catalogue and fall back to manual
 * selection when it is invalid or low-confidence (security baseline §7).
 */
final readonly class ScopingSuggestion
{
    /**
     * @param  float  $confidence  0.0–1.0
     * @param  array<string, string|list<string>>  $prefilledAnswers  keyed by scoping question key
     */
    public function __construct(
        public string $tradeKey,
        public string $serviceKey,
        public float $confidence,
        public array $prefilledAnswers = [],
    ) {}
}
