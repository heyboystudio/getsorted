<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Models\ServiceJobInvite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** WhatsApps a pro about a new invite: trade, area and a link only, never customer details (spec 009, AC1). */
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

    public function handle(MessagingChannel $messaging): void
    {
        $invite = ServiceJobInvite::query()->with(['pro.user', 'serviceJob.trade'])->find($this->inviteId);

        if (! $invite instanceof ServiceJobInvite || ! $invite->isAvailable() || $invite->pro->user->phone_e164 === null) {
            return;
        }

        $messaging->send(new OutgoingMessage($invite->pro->user->phone_e164, 'job_invite', [
            'service' => $invite->serviceJob->trade->name,
            'suburb' => (string) $invite->serviceJob->area_label,
            'link' => route('pros.jobs.show', $invite),
        ]));
    }
}
