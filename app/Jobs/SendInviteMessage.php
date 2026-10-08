<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Matching\Support\Distance;
use App\Domain\Notifications\Notify;
use App\Models\ServiceJobInvite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells a pro about a new invite: trade, area and a link only, never customer details (spec 009, AC1). */
final class SendInviteMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $inviteId,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $invite = ServiceJobInvite::query()->with(['pro.user', 'serviceJob.trade'])->find($this->inviteId);

        if (! $invite instanceof ServiceJobInvite || ! $invite->isAvailable()) {
            return;
        }

        // The in-app notice and email go once, even if the job is retried.
        if ($this->attempts() === 1) {
            $job = $invite->serviceJob;
            $near = $invite->pro->base_location !== null && $job->location !== null ? ' · '.Distance::label($invite->pro->base_location, $job->location) : '';
            Notify::user(
                $invite->pro->user,
                'job_invite',
                __('New :trade job near you', ['trade' => mb_strtolower($job->trade->name)]),
                trim(implode(' · ', array_filter([implode(', ', array_slice($job->factTexts(), 0, 2)), (string) $job->area_label])).$near, ' ·').'. '.__('Quote if you can help. The first quotes win.'),
                route('pros.jobs.show', $invite),
                email: true,
            );
        }
    }
}
