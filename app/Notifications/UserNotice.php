<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->email && filled($notifiable->email) && $notifiable->email_verified_at !== null ? ['database', 'mail'] : ['database'];
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
            ->action(__('Open on Get Sorted'), $this->url)
            ->line(__('You are receiving this because you have a Get Sorted account.'));
    }
}
