<?php

declare(strict_types=1);

namespace App\Domain\Operations\Support;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Jobs that need an admin's attention (spec 027, AC1–AC3): open jobs with no estimate after a while
 * (sooner when urgent), and booked jobs past their start date that nobody has marked done.
 */
final class StalledJobs
{
    public const string NO_ESTIMATE = 'no_estimate';

    public const string NOT_FINISHED = 'not_finished';

    /** @return Collection<int, array{job: ServiceJob, reason: string, label: string, since: CarbonImmutable}> oldest first */
    public static function all(): Collection
    {
        $stalled = new Collection;

        ServiceJob::query()->with('trade')
            ->where('status', ServiceJobStatus::Open->value)->where('quotes_count', 0)->whereNotNull('posted_at')
            ->each(function (ServiceJob $job) use ($stalled): void {
                $hours = (int) config($job->isUrgent() ? 'getsorted.ops.urgent_no_estimate_hours' : 'getsorted.ops.no_estimate_hours');

                if ($job->posted_at?->lte(now()->subHours($hours)) === true) {
                    $stalled->push(['job' => $job, 'reason' => self::NO_ESTIMATE, 'label' => (string) __('No estimate after :hours h', ['hours' => $hours]).($job->isUrgent() ? ' · '.__('urgent') : ''), 'since' => $job->posted_at]);
                }
            });

        $days = (int) config('getsorted.ops.finish_overdue_days');
        ServiceJob::query()->with('trade')
            ->whereIn('status', [ServiceJobStatus::Scheduled->value, ServiceJobStatus::InProgress->value])
            ->whereDate('scheduled_for', '<=', LocalTime::today()->subDays($days)->toDateString())
            ->each(function (ServiceJob $job) use ($stalled): void {
                $stalled->push(['job' => $job, 'reason' => self::NOT_FINISHED, 'label' => (string) __('Booked, not marked done'), 'since' => $job->scheduled_for->toImmutable()]);
            });

        return $stalled->sortBy(fn (array $row): int => $row['since']->getTimestamp())->values();
    }
}
