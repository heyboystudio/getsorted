<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushChannel;
use Throwable;

/**
 * Sends the pop-up through the web push package but never lets a push problem stop the in-app notice or
 * the email, or make the queued job retry them (spec 022, AC5). Gone subscriptions (404, 410) are deleted
 * by the package itself; here only the kind of failure is logged, never an endpoint or payload.
 */
final readonly class SafeWebPushChannel
{
    public function __construct(private WebPushChannel $channel) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        try {
            $this->channel->send($notifiable, $notification);
        } catch (Throwable $exception) {
            Log::warning('Web push could not be sent.', ['exception' => $exception::class]);
        }
    }
}
