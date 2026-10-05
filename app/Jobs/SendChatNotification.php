<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\JobConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * "You have a new message" by WhatsApp/SMS (spec 018, AC4). Never the message
 * itself; at most one per conversation and person in the notify window, and
 * none while the person has the chat open.
 */
final class SendChatNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $conversationId,
        public readonly MessageSender $recipient,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $toCustomer = $this->recipient === MessageSender::Customer;
        $readColumn = $toCustomer ? 'customer_read_at' : 'pro_read_at';
        $notifiedColumn = $toCustomer ? 'customer_notified_at' : 'pro_notified_at';

        $send = DB::transaction(function () use ($readColumn, $notifiedColumn): ?JobConversation {
            $conversation = JobConversation::query()->with(['serviceJob.customer', 'serviceJob.service', 'pro.user'])->lockForUpdate()->find($this->conversationId);

            if (! $conversation instanceof JobConversation || JobChat::unreadFor($conversation, $this->recipient) === 0) {
                return null;
            }

            $onPage = $conversation->{$readColumn}?->gt(now()->subSeconds((int) config('sortd.chat.online_seconds'))) === true;
            $recent = $conversation->{$notifiedColumn}?->gt(now()->subMinutes((int) config('sortd.chat.notify_every_minutes'))) === true;

            if ($onPage || $recent) {
                return null;
            }

            $conversation->forceFill([$notifiedColumn => now()])->save();

            return $conversation;
        });

        if (! $send instanceof JobConversation) {
            return;
        }

        $job = $send->serviceJob;
        $phone = $toCustomer ? $job->customer->phone_e164 : $send->pro->user->phone_e164;
        $invite = JobChat::inviteFor($job, $send->pro);

        if ($phone === null || (! $toCustomer && $invite === null)) {
            return;
        }

        $messaging->send(new OutgoingMessage($phone, 'chat_message', [
            'service' => $job->service->name,
            'link' => $toCustomer ? route('jobs.show', $job) : route('pros.jobs.show', $invite),
        ]));
    }
}
