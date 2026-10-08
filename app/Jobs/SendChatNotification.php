<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notifications\Notify;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\JobConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * "You have a new message" notice (spec 018, AC4). Never the message
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

    public function handle(): void
    {
        $toCustomer = $this->recipient === MessageSender::Customer;
        $readColumn = $toCustomer ? 'customer_read_at' : 'pro_read_at';
        $notifiedColumn = $toCustomer ? 'customer_notified_at' : 'pro_notified_at';

        $send = DB::transaction(function () use ($readColumn, $notifiedColumn): ?JobConversation {
            $conversation = JobConversation::query()->with(['serviceJob.customer', 'serviceJob.trade', 'pro.user'])->lockForUpdate()->find($this->conversationId);

            if (! $conversation instanceof JobConversation || JobChat::unreadFor($conversation, $this->recipient) === 0) {
                return null;
            }

            $onPage = $conversation->{$readColumn}?->gt(now()->subSeconds((int) config('getsorted.chat.online_seconds'))) === true;
            $recent = $conversation->{$notifiedColumn}?->gt(now()->subMinutes((int) config('getsorted.chat.notify_every_minutes'))) === true;

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
        $invite = JobChat::inviteFor($job, $send->pro);

        if (! $toCustomer && $invite === null) {
            return;
        }

        if ($this->attempts() === 1) {
            Notify::user($toCustomer ? $job->customer : $send->pro->user, 'chat_message', __('New message about your :trade job', ['trade' => mb_strtolower($job->trade->name)]), __('Open the chat to read and reply.'), $toCustomer ? route('jobs.show', $job) : route('pros.jobs.show', $invite), group: $toCustomer ? 'messages' : null);
        }
    }
}
