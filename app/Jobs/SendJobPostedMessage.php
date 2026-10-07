<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Support\NotificationPreferences;
use App\Models\ServiceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells the customer their job is posted (spec 005, AC11). Queued after commit; thin. */
final class SendJobPostedMessage implements ShouldQueue
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
        $job = ServiceJob::query()->with(['customer', 'service'])->find($this->serviceJobId);

        if ($job === null || $job->customer->phone_e164 === null || ! NotificationPreferences::allows($job->customer, 'job_updates')) {
            return;
        }

        $messaging->send(new OutgoingMessage($job->customer->phone_e164, 'job_posted', ['service' => $job->service->name], NotificationPreferences::channel($job->customer)));
    }
}
