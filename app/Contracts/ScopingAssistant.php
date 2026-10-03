<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\ScopingSuggestion;

/**
 * AI help for describing a job. Implementations receive only the service,
 * scoping answers and the customer's description with phone numbers and
 * emails already stripped; never addresses, names, IDs or payment data.
 */
interface ScopingAssistant
{
    /**
     * Suggest a trade and service from the customer's own words, or null when unsure.
     *
     * @param  array<string, list<string>>  $catalogue  service keys grouped by trade key
     */
    public function suggestService(string $description, array $catalogue): ?ScopingSuggestion;

    /**
     * A neutral 2–3 sentence summary for pros, or null if the model output is unusable.
     *
     * @param  array<string, string|list<string>>  $answers  keyed by scoping question key
     */
    public function summarise(string $serviceKey, array $answers, string $description): ?string;
}
