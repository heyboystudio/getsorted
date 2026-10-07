<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Accounts\Support\NotificationPreferences;
use App\Models\User;
use App\Notifications\Channels\SafeWebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One notice to a client or a pro: always shown in their notifications, and emailed too for the moments that
 * matter (a new job near a pro, a quote for a client). Text carries trade, area and a link only, never contact
 * details or street addresses (security baseline §3).
 */
final class UserNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly bool $email = false,
        public readonly ?string $group = null,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $channels = $this->email && filled($notifiable->email) && $notifiable->email_verified_at !== null ? ['database', 'mail'] : ['database'];

        return $this->pushes($notifiable) ? [...$channels, SafeWebPushChannel::class] : $channels;
    }

    /**
     * A pop-up on this person's devices (spec 022): only when push is set up on the server, they have a
     * subscribed device, and a customer has not switched this kind of notice off.
     */
    private function pushes(User $notifiable): bool
    {
        if (! filled(config('webpush.vapid.public_key')) || ! filled(config('webpush.vapid.private_key'))) {
            return false;
        }

        if ($this->group !== null && ! NotificationPreferences::allows($notifiable, $this->group)) {
            return false;
        }

        return $notifiable->pushSubscriptions()->exists();
    }

    /**
     * The same safe text as the inbox and email: trade, area or wording and a link, never contact details
     * or message text (security baseline §3). Notices of one kind for one page replace each other.
     */
    public function toWebPush(User $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('icons/icon-192.png'))
            ->badge(asset('icons/badge-96.png'))
            ->tag($this->kind.'-'.substr(md5($this->url), 0, 10))
            ->data(['url' => $this->url])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }

    /** @return array{kind: string, title: string, body: string, url: string} */
    public function toDatabase(User $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line($this->body)
            ->action(__('Open on GetSorted'), $this->url)
            ->line(__('You are receiving this because you have a GetSorted account.'));
    }
}
