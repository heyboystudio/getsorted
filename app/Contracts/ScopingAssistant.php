<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\ScopingSuggestionReply;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;

/**
 * AI help for describing a job. Implementations receive only the service,
 * scoping answers and the customer's description with phone numbers and
 * emails already stripped; never addresses, names, IDs or payment data.
 * Replies are suggestions: callers validate them before use (security baseline §7).
 */
interface ScopingAssistant
{
    /**
     * Suggest a trade and service from the customer's own words; the reply's suggestion is null when unsure.
     *
     * @param  array<string, list<string>>  $catalogue  service keys grouped by trade key
     *
     * @throws AssistantUnavailable
     */
    public function suggestService(string $description, array $catalogue): ScopingSuggestionReply;

    /**
     * A neutral 2–3 sentence summary for pros; the reply's summary is null if the model output is unusable.
     *
     * @param  array<string, string|list<string>>  $answers  keyed by scoping question key
     *
     * @throws AssistantUnavailable
     */
    public function summarise(string $serviceKey, array $answers, string $description): ScopingSummaryReply;
}
