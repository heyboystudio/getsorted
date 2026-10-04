<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Models\ServiceJob;

/** The parts of a draft a job summary describes, and a fingerprint to tell when they change (spec 007, AC5, AC7). */
final class JobSummaryInput
{
    public static function hash(ServiceJob $job): string
    {
        return hash('sha256', (string) json_encode([$job->service_id, self::answers($job), trim((string) $job->customer_notes)]));
    }

    /** @return array<string, string|list<string>> answers keyed by question key, as the assistant receives them */
    public static function answers(ServiceJob $job): array
    {
        $answers = [];

        foreach ($job->scoping_answers as $key => $answer) {
            $answers[$key] = is_array($answer['answer']) ? array_map(strval(...), $answer['answer']) : (string) $answer['answer'];
        }

        ksort($answers);

        return $answers;
    }
}
