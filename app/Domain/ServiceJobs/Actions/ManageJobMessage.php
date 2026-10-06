<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\MessageReportReason;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\User;

/**
 * What participants can do to messages after sending (spec 018): mark a chat read,
 * or report the other side's (AC6). Messages are never deleted: the record protects both sides.
 */
final class ManageJobMessage
{
    public function markRead(User $user, JobConversation $conversation): void
    {
        $side = $this->side($user, $conversation);
        $conversation->forceFill([$side === MessageSender::Customer ? 'customer_read_at' : 'pro_read_at' => now()])->save();
    }

    public function report(User $user, JobMessage $message, MessageReportReason $reason): void
    {
        $side = $this->side($user, $message->conversation);
        abort_if($message->sender_type === $side || $message->sender_type === MessageSender::System, 404);

        if ($message->reported_at !== null) {
            return;
        }

        $message->forceFill(['reported_at' => now(), 'report_reason' => $reason, 'reported_by' => $user->id])->save();
        activity()->causedBy($user)->performedOn($message->conversation->serviceJob)
            ->withProperties(['message' => $message->public_id, 'reason' => $reason->value])->log('chat_message_reported');
    }

    private function side(User $user, JobConversation $conversation): MessageSender
    {
        $side = JobChat::sideOf($user, $conversation->serviceJob, $conversation->pro);
        abort_unless($side instanceof MessageSender, 404);

        return $side;
    }
}
