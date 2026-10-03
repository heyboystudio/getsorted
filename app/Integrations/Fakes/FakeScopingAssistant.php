<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\ScopingAssistant;

/**
 * Deterministic stand-in for the AI assistant. Returns nothing unless a test
 * scripts a response, so callers' "no suggestion" fallback is the default path.
 * Tests can script invalid output (unknown keys, hostile text) to check fallbacks.
 */
final class FakeScopingAssistant implements ScopingAssistant
{
    private ?ScopingSuggestion $suggestion = null;

    private ?string $summary = null;

    /** @var list<string> */
    private array $descriptionsSeen = [];

    public function willSuggest(?ScopingSuggestion $suggestion): self
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    public function willSummarise(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function suggestService(string $description, array $catalogue): ?ScopingSuggestion
    {
        $this->descriptionsSeen[] = $description;

        return $this->suggestion;
    }

    public function summarise(string $serviceKey, array $answers, string $description): ?string
    {
        $this->descriptionsSeen[] = $description;

        return $this->summary;
    }

    /**
     * Every description passed to the assistant, so tests can prove personal data was stripped.
     *
     * @return list<string>
     */
    public function descriptionsSeen(): array
    {
        return $this->descriptionsSeen;
    }
}
