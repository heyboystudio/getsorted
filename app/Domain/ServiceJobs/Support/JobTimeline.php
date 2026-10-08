<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a customer may see of a job's event log (spec 021, AC8 and AC10). An allow-list: an
 * event type that is not listed here is hidden, so internal or admin events never leak.
 */
final class JobTimeline
{
    /**
     * Customer wording per event type.
     *
     * @return array<string, string>
     */
    public static function wording(): array
    {
        return [
            'job_posted' => __('You posted your request'),
            'quote_accepted' => __('You accepted a quote'),
            'job_cancelled_by_customer' => __('You cancelled this job'),
            'job_done' => __('The job was marked done'),
            'booking_cancelled_by_customer' => __('You cancelled this booking'),
            'booking_cancelled_by_pro' => __('Your pro cancelled this booking'),
            'job_expired' => __('No quote was accepted in time'),
            'final_amount_proposed' => __('Your pro proposed a new final price'),
            'final_amount_lowered' => __('Your pro lowered the final price'),
            'final_amount_approved' => __('You approved the new final price'),
            'final_amount_declined' => __('You declined the new final price'),
            'cancelled_price_not_agreed' => __('Cancelled: the price was not agreed'),
        ];
    }

    /**
     * Newest last, as a timeline reads.
     *
     * @return Collection<int, array{text: string, at: CarbonImmutable}>
     */
    public static function forJob(ServiceJob $job): Collection
    {
        return ServiceJobEvent::query()
            ->where('service_job_id', $job->id)
            ->whereIn('event_type', array_keys(self::wording()))
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceJobEvent $event): array => ['text' => self::wording()[$event->event_type], 'at' => $event->created_at])
            ->values();
    }

    /**
     * The customer's latest activity across all their jobs, newest first.
     *
     * @return Collection<int, array{text: string, service: string, job: string, at: CarbonImmutable}>
     */
    public static function recentFor(User $customer, int $limit = 5): Collection
    {
        return ServiceJobEvent::query()
            ->whereHas('serviceJob', fn ($job) => $job->where('customer_id', $customer->id))
            ->whereIn('event_type', array_keys(self::wording()))
            ->with('serviceJob.trade')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ServiceJobEvent $event): array => [
                'text' => self::wording()[$event->event_type],
                'service' => $event->serviceJob->trade->name,
                'job' => $event->serviceJob->public_id,
                'at' => $event->created_at,
            ])
            ->values();
    }
}
