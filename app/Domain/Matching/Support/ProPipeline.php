<?php

declare(strict_types=1);

namespace App\Domain\Matching\Support;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use Illuminate\Support\Collection;

/**
 * Sorts a pro's invites into the four places a job can be in their working life (spec 021, AC20
 * and AC23): Invites to answer, Quoted and waiting, Booked, and Done. One pass, so the Today
 * screen and the Jobs tabs always agree.
 */
final class ProPipeline
{
    public const array STAGES = ['invites', 'quoted', 'booked', 'done'];

    /** Statuses in which an accepted job is still the pro's to deliver. */
    private const array BOOKED = [
        ServiceJobStatus::AwaitingDeposit, ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress,
        ServiceJobStatus::AwaitingFinalPayment, ServiceJobStatus::Disputed,
    ];

    /**
     * @return array<string, Collection<int, array{invite: ServiceJobInvite, job: ServiceJob, quote: ?Quote, stage: string, note: ?string}>>
     */
    public static function for(Pro $pro, int $limit = 200): array
    {
        $invites = ServiceJobInvite::query()
            ->where('pro_id', $pro->id)
            ->with(['serviceJob.trade', 'serviceJob.property', 'pro'])
            ->latest('invited_at')
            ->limit($limit)
            ->get();

        /** @var Collection<int, Quote> $myQuotes The pro's newest quote on each job (older versions are superseded). */
        $myQuotes = Quote::query()->where('pro_id', $pro->id)->whereIn('service_job_id', $invites->pluck('service_job_id'))
            ->orderBy('id')->get()->keyBy('service_job_id');

        /** @var Collection<int, array{invite: ServiceJobInvite, job: ServiceJob, quote: ?Quote, stage: string, note: ?string}> $rows */
        $rows = new Collection;

        foreach ($invites as $invite) {
            $job = $invite->serviceJob;

            if (! $job instanceof ServiceJob) {
                continue;
            }

            $quote = $myQuotes->get($invite->service_job_id);
            [$stage, $note] = self::classify($invite, $job, $quote);
            $rows->push(['invite' => $invite, 'job' => $job, 'quote' => $quote, 'stage' => $stage, 'note' => $note]);
        }

        $grouped = [];
        foreach (self::STAGES as $stage) {
            $grouped[$stage] = $rows->where('stage', $stage)->values();
        }

        // Answer the job that runs out first; deliver the job that is due first.
        $grouped['invites'] = $grouped['invites']->sortBy(fn (array $row): int => $row['invite']->expires_at->getTimestamp())->values();
        $grouped['booked'] = $grouped['booked']->sortBy(fn (array $row): string => (string) $row['job']->scheduled_for?->toDateString())->values();

        return $grouped;
    }

    /** @return array{0: string, 1: ?string} stage and the reason shown on past jobs */
    private static function classify(ServiceJobInvite $invite, ServiceJob $job, ?Quote $quote): array
    {
        $won = $quote instanceof Quote && $job->accepted_quote_id === $quote->id;

        if ($won && in_array($job->status, self::BOOKED, true)) {
            return ['booked', null];
        }

        if ($won) {
            return ['done', match ($job->status) {
                ServiceJobStatus::Completed, ServiceJobStatus::Closed => __('Completed'),
                ServiceJobStatus::Cancelled => __('Cancelled'),
                default => __('Closed'),
            }];
        }

        if ($job->status === ServiceJobStatus::Open && $job->accepted_quote_id === null) {
            if ($quote instanceof Quote && in_array($quote->status, [QuoteStatus::Submitted, QuoteStatus::Expired], true)) {
                return ['quoted', null];
            }

            if ($invite->isAvailable()) {
                return ['invites', null];
            }
        }

        return ['done', self::pastReason($invite, $job, $quote)];
    }

    private static function pastReason(ServiceJobInvite $invite, ServiceJob $job, ?Quote $quote): string
    {
        return match (true) {
            $quote?->status === QuoteStatus::Withdrawn => __('You withdrew your quote'),
            $quote instanceof Quote && $job->accepted_quote_id !== null => __('Quote not chosen'),
            $quote?->status === QuoteStatus::Declined => __('Quote not chosen'),
            $invite->status === InviteStatus::Declined => __('You declined'),
            $job->status === ServiceJobStatus::Cancelled => __('Cancelled'),
            $job->status === ServiceJobStatus::Expired => __('Expired'),
            $invite->status === InviteStatus::Closed => __('Closed'),
            default => __('Expired'),
        };
    }
}
