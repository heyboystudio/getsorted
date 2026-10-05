<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\MessageReportReason;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What participants can do to messages after sending (spec 018): mark a chat read,
 * delete their own message within a few minutes, or report the other side's (AC6).
 */
final class ManageJobMessage
{
    public function markRead(User $user, JobConversation $conversation): void
    {
        $side = $this->side($user, $conversation);
        $conversation->forceFill([$side === MessageSender::Customer ? 'customer_read_at' : 'pro_read_at' => now()])->save();
    }

    public function delete(User $user, JobMessage $message): void
    {
        abort_unless($message->sender_id === $user->id && $message->deleted_at === null, 404);
        $this->side($user, $message->conversation);

        if ($message->created_at->lt(now()->subMinutes((int) config('sortd.chat.delete_within_minutes')))) {
            throw ValidationException::withMessages(['message' => __('Messages can only be deleted in the first :minutes minutes.', ['minutes' => config('sortd.chat.delete_within_minutes')])]);
        }

        DB::transaction(function () use ($message): void {
            $message->clearMediaCollection(JobMessage::PHOTO_COLLECTION);
            $message->forceFill(['body' => null, 'deleted_at' => now()])->save();
        });
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
