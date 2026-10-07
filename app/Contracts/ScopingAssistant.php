<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
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
     * A neutral 2–3 sentence summary for pros; the reply's summary is null if the model output is unusable.
     *
     * @param  list<string>  $facts  the facts extracted from the customer's words, personal data already stripped
     *
     * @throws AssistantUnavailable
     */
    public function summarise(string $tradeName, array $facts, string $description): ScopingSummaryReply;

    /**
     * One turn of the Siya booking chat (spec 020): the model changes the booking state through the request's toolbox and returns a reply.
     *
     * @throws AssistantUnavailable
     */
    public function chat(ChatRequest $request): ChatReply;
}
