<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Models\ServiceJob;

/**
 * How a job is named in lists and notices now that jobs have a trade and extracted facts instead of
 * a service (spec 020): "Plumbing", or "Plumbing · leaking geyser" once Siya has a first fact.
 */
final class JobLabel
{
    public static function for(ServiceJob $job): string
    {
        $fact = $job->factTexts()[0] ?? null;

        return $fact === null || $fact === '' ? $job->trade->name : $job->trade->name.' · '.$fact;
    }

    /** The approximate area: the job's own copy once posted, else the saved property's. */
    public static function area(ServiceJob $job): string
    {
        return (string) ($job->area_label ?? data_get($job, 'property.area_label') ?? __('No address yet'));
    }
}
