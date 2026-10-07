<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\Assistant\Support\Redactor;
use App\Models\ServiceJob;

/** The parts of a draft a job summary describes, and a fingerprint to tell when they change (spec 007, AC5, AC7; spec 020). */
final class JobSummaryInput
{
    public static function hash(ServiceJob $job): string
    {
        return hash('sha256', (string) json_encode([$job->trade_id, $job->factTexts(), trim((string) $job->customer_notes)]));
    }

    /**
     * The facts as the assistant receives them: customer text can hold contact details or addresses (spec 007, AC10).
     *
     * @return list<string>
     */
    public static function redactedFacts(ServiceJob $job): array
    {
        return array_map(Redactor::strip(...), $job->factTexts());
    }
}
