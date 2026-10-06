<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Notifications\Notify;
use App\Models\ServiceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells the customer no quote was accepted in time, with a link to post again (spec 010, AC11). */
final class SendJobExpiredMessage implements ShouldQueue
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

        if (! $job instanceof ServiceJob) {
            return;
        }

        if ($this->attempts() === 1) {
            Notify::user($job->customer, 'job_expired', __('Your :trade job ran out of time', ['trade' => mb_strtolower($job->trade->name)]), __('No quote was accepted in time. You can post it again whenever you are ready.'), route('book.trade', $job->trade), email: true);
        }

        if ($job->customer->phone_e164 === null) {
            return;
        }

        $messaging->send(new OutgoingMessage($job->customer->phone_e164, 'job_expired', [
            'service' => $job->trade->name,
            'link' => route('book.trade', $job->trade),
        ]));
    }
}
