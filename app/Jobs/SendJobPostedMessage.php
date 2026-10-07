<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Support\NotificationPreferences;
use App\Domain\Notifications\Notify;
use App\Models\ServiceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells the customer their job is posted (spec 005, AC11). Queued after commit; thin. */
final class SendJobPostedMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Twilio rate-limits bursts (429), so retry after a pause rather than at once.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $serviceJobId,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $job = ServiceJob::query()->with(['customer', 'trade'])->find($this->serviceJobId);

        if ($job === null) {
            return;
        }

        if ($this->attempts() === 1) {
            Notify::user($job->customer, 'job_posted', __('Your :trade job is posted', ['trade' => mb_strtolower($job->trade->name)]), __('We are sharing it with vetted pros near you. Quotes will show up on your job page.'), route('jobs.show', $job));
        }

        if ($job->customer->phone_e164 === null || ! NotificationPreferences::allows($job->customer, 'job_updates')) {
            return;
        }

        $messaging->send(new OutgoingMessage($job->customer->phone_e164, 'job_posted', ['service' => $job->trade->name], NotificationPreferences::channel($job->customer)));
    }
}
