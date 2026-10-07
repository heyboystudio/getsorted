<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The rules of job chat (spec 018) in one place: who may read or write each
 * conversation, when contact details are masked, and what customers see a pro
 * called before that pro has spoken (spec 009 AC13: invitees stay anonymous).
 */
final class JobChat
{
    /** Invites a customer can message, or that can message the customer, while the job collects quotes. */
    private const array CHATTY_INVITES = [InviteStatus::Invited, InviteStatus::Viewed, InviteStatus::Accepted, InviteStatus::Quoted];

    /** The side this user is on in a job's chat with this pro, or null if they're not part of it. */
    public static function sideOf(User $user, ServiceJob $job, Pro $pro): ?MessageSender
    {
        if ($job->customer_id === $user->id) {
            return MessageSender::Customer;
        }

        if ($pro->user_id === $user->id && $pro->status === ProStatus::Approved && self::inviteFor($job, $pro) instanceof ServiceJobInvite) {
            return MessageSender::Pro;
        }

        return null;
    }

    /** Whether new messages can be sent between this job's customer and this pro right now (AC1, AC5). */
    public static function canWrite(ServiceJob $job, Pro $pro, ?JobConversation $conversation = null): bool
    {
        if (! in_array($job->status, JobConversation::CHAT_STATUSES, true) || $conversation?->status === 'closed') {
            return false;
        }

        if ($pro->status !== ProStatus::Approved) {
            return false;
        }

        if ($job->accepted_quote_id !== null) {
            return self::isAcceptedPro($job, $pro);
        }

        $invite = self::inviteFor($job, $pro);

        return $invite instanceof ServiceJobInvite && in_array($invite->status, self::CHATTY_INVITES, true);
    }

    /** Contact details are shared once this pro's quote is accepted (spec 010); until then text is masked (AC3). */
    public static function contactShared(ServiceJob $job, Pro $pro): bool
    {
        return $job->accepted_quote_id !== null && self::isAcceptedPro($job, $pro);
    }

    public static function isAcceptedPro(ServiceJob $job, Pro $pro): bool
    {
        return $job->accepted_quote_id !== null
            && Quote::query()->whereKey($job->accepted_quote_id)->where('pro_id', $pro->id)->exists();
    }

    public static function inviteFor(ServiceJob $job, Pro $pro): ?ServiceJobInvite
    {
        return ServiceJobInvite::query()->where('service_job_id', $job->id)->where('pro_id', $pro->id)->first();
    }

    /**
     * The pros a customer can see in their job's chat list: those they can message now,
     * plus any with an existing conversation (read-only after another pro is chosen).
     *
     * @return Collection<int, array{pro: Pro, label: string, named: bool, conversation: ?JobConversation, writable: bool, unread: int}>
     */
    public static function customerList(ServiceJob $job): Collection
    {
        $conversations = $job->conversations()->get()->keyBy('pro_id');
        $quoted = $job->quotes()->pluck('pro_id')->unique()->all();

        $invites = ServiceJobInvite::query()->with('pro')->where('service_job_id', $job->id)
            ->orderBy('invited_at')->orderBy('id')->get();

        return $invites->values()->map(function (ServiceJobInvite $invite, int $index) use ($job, $conversations, $quoted): ?array {
            $conversation = $conversations->get($invite->pro_id);

            if (! $invite->pro instanceof Pro) {
                return null;
            }

            $writable = self::canWrite($job, $invite->pro, $conversation);

            if (! $writable && ! $conversation instanceof JobConversation) {
                return null;
            }

            $named = in_array($invite->pro_id, $quoted, true) || ($conversation instanceof JobConversation && $conversation->messages()->where('sender_type', MessageSender::Pro)->exists());

            return [
                'pro' => $invite->pro,
                'label' => $named && $invite->pro->business_name !== null ? $invite->pro->business_name : (string) __('Pro :letter', ['letter' => self::letter($index)]),
                'named' => $named,
                'conversation' => $conversation,
                'writable' => $writable,
                'unread' => $conversation instanceof JobConversation ? self::unreadFor($conversation, MessageSender::Customer) : 0,
            ];
        })->filter()->values();
    }

    /**
     * Every conversation this person is in, newest activity first, with what the inbox needs to show a row.
     *
     * @return Collection<int, array{conversation: JobConversation, job: ServiceJob, label: string, last: ?JobMessage, unread: int, url: string}>
     */
    public static function inboxFor(User $user): Collection
    {
        $asPro = $user->hasRole(Role::Pro->value);

        $conversations = JobConversation::query()
            ->with(['serviceJob.trade', 'pro'])
            ->when($asPro, fn ($query) => $query->whereHas('pro', fn ($pro) => $pro->where('user_id', $user->id)))
            ->when(! $asPro, fn ($query) => $query->whereHas('serviceJob', fn ($job) => $job->where('customer_id', $user->id)))
            ->whereHas('messages')
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->limit(100)->get();

        $labels = [];

        return $conversations->map(function (JobConversation $conversation) use ($asPro, &$labels): array {
            $job = $conversation->serviceJob()->with('trade')->firstOrFail();
            $side = $asPro ? MessageSender::Pro : MessageSender::Customer;

            if ($asPro) {
                $label = (string) __(':trade job · :area', ['trade' => $job->trade->name, 'area' => (string) $job->area_label]);
                $invite = self::inviteFor($job, $conversation->pro);
                $url = $invite instanceof ServiceJobInvite ? route('pros.jobs.show', $invite).'#chat' : route('pros.jobs');
            } else {
                $labels[$job->id] ??= self::customerList($job)->mapWithKeys(fn (array $row): array => [$row['pro']->id => $row['label']])->all();
                $label = (string) ($labels[$job->id][$conversation->pro_id] ?? __('Pro'));
                $url = route('jobs.show', $job).'#chats';
            }

            return [
                'conversation' => $conversation,
                'job' => $job,
                'label' => $label,
                'last' => $conversation->messages()->with('media')->latest('id')->first(),
                'unread' => self::unreadFor($conversation, $side),
                'url' => $url,
            ];
        });
    }

    /** Unread messages across all of a person's chats (the badge on the Messages link). */
    public static function unreadTotal(User $user): int
    {
        $asPro = $user->hasRole(Role::Pro->value);
        $readColumn = $asPro ? 'pro_read_at' : 'customer_read_at';

        return JobMessage::query()
            ->whereNull('deleted_at')
            ->where('sender_type', $asPro ? MessageSender::Customer : MessageSender::Pro)
            ->whereHas('conversation', function ($conversation) use ($user, $asPro, $readColumn): void {
                $conversation
                    ->when($asPro, fn ($query) => $query->whereHas('pro', fn ($pro) => $pro->where('user_id', $user->id)))
                    ->when(! $asPro, fn ($query) => $query->whereHas('serviceJob', fn ($job) => $job->where('customer_id', $user->id)));
                $conversation->where(fn ($q) => $q->whereNull($readColumn)->orWhereColumn('job_messages.created_at', '>', $readColumn));
            })
            ->count();
    }

    /** Messages the other side sent after this side last looked. */
    public static function unreadFor(JobConversation $conversation, MessageSender $side): int
    {
        $readAt = $side === MessageSender::Customer ? $conversation->customer_read_at : $conversation->pro_read_at;

        return $conversation->messages()
            ->where('sender_type', $side === MessageSender::Customer ? MessageSender::Pro : MessageSender::Customer)
            ->whereNull('deleted_at')
            ->when($readAt !== null, fn ($query) => $query->where('created_at', '>', $readAt))
            ->count();
    }

    /** The text to show for a message to anyone: deleted messages keep their place but lose their content. */
    public static function shownBody(JobMessage $message): ?string
    {
        return $message->deleted_at !== null ? __('Message deleted') : $message->body;
    }

    private static function letter(int $index): string
    {
        return $index < 26 ? chr(65 + $index) : (string) ($index + 1);
    }
}
