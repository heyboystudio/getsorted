<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Models\ServiceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells the customer no quote was accepted in time, with a link to post again (spec 010, AC11). */
final class SendJobExpiredMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $serviceJobId,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $job = ServiceJob::query()->with(['customer', 'trade'])->find($this->serviceJobId);

        if (! $job instanceof ServiceJob || $job->customer->phone_e164 === null) {
            return;
        }

        $messaging->send(new OutgoingMessage($job->customer->phone_e164, 'job_expired', [
            'service' => $job->trade->name,
            'link' => route('book.trade', $job->trade),
        ]));
    }
}
