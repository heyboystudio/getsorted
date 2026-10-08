<?php

declare(strict_types=1);

namespace App\Domain\Operations\Support;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Quote;
use App\Models\Review;
use App\Models\ServiceJob;
use Carbon\CarbonImmutable;

/**
 * The PRD's success measures (section 5) over the last N days of posted jobs (spec 027, AC4–AC5).
 * A measure is null until there is something to measure, so the dashboard never shows an invented number.
 */
final class SuccessMeasures
{
    /**
     * @return array<string, array{label: string, value: float|null, display: string, target: string, met: bool|null, sample: int}>
     */
    public static function over(int $days = 90, ?CarbonImmutable $now = null): array
    {
        $since = ($now ?? CarbonImmutable::now())->subDays($days);
        $jobs = ServiceJob::query()->whereNotNull('posted_at')->where('posted_at', '>=', $since)->get(['id', 'posted_at', 'accepted_quote_id', 'status', 'quotes_count']);
        $jobIds = $jobs->pluck('id');

        $firstQuoteAt = Quote::query()->whereIn('service_job_id', $jobIds)->selectRaw('service_job_id, min(submitted_at) as first_at')->groupBy('service_job_id')->pluck('first_at', 'service_job_id');
        $quotesPerJob = Quote::query()->whereIn('service_job_id', $jobIds)->where('version', 1)->selectRaw('service_job_id, count(*) as total')->groupBy('service_job_id')->pluck('total', 'service_job_id');

        $hours = $jobs->filter(fn (ServiceJob $job): bool => $firstQuoteAt->has($job->id))
            ->map(fn (ServiceJob $job): float => max(0, $job->posted_at->diffInMinutes(CarbonImmutable::parse((string) $firstQuoteAt[$job->id]), true)) / 60)
            ->sort()->values();

        $withQuotes = $quotesPerJob->count();
        $twoOrMore = $quotesPerJob->filter(fn ($total): bool => (int) $total >= 2)->count();
        $accepted = $jobs->whereNotNull('accepted_quote_id');
        $done = $accepted->where('status', ServiceJobStatus::Completed);
        $reviewed = Review::query()->whereIn('service_job_id', $done->pluck('id'))->count();

        $median = $hours->isEmpty() ? null : (float) $hours->median();

        return [
            'first_quote' => self::row(__('Median time to first estimate'), $median, $median === null ? '—' : number_format($median, 1).' h', __('under 4 h'), $median === null ? null : $median < 4, $hours->count()),
            'two_quotes' => self::percent(__('Jobs with 2 or more estimates'), $twoOrMore, $jobs->count(), __('over 60%'), 60),
            'accept' => self::percent(__('Jobs with an estimate that chose a pro'), $accepted->count(), $withQuotes, __('over 35%'), 35),
            'done' => self::percent(__('Booked jobs marked done'), $done->count(), $accepted->count(), __('over 70%'), 70),
            'reviewed' => self::percent(__('Done jobs reviewed'), $reviewed, $done->count(), __('over 40%'), 40),
        ];
    }

    /** @return array{label: string, value: float|null, display: string, target: string, met: bool|null, sample: int} */
    private static function percent(string $label, int $part, int $whole, string $target, int $threshold): array
    {
        $value = $whole === 0 ? null : round($part / $whole * 100, 1);

        return self::row($label, $value, $value === null ? '—' : number_format($value, 0).'%', $target, $value === null ? null : $value > $threshold, $whole);
    }

    /** @return array{label: string, value: float|null, display: string, target: string, met: bool|null, sample: int} */
    private static function row(string $label, ?float $value, string $display, string $target, ?bool $met, int $sample): array
    {
        return ['label' => $label, 'value' => $value, 'display' => $display, 'target' => $target, 'met' => $met, 'sample' => $sample];
    }
}
