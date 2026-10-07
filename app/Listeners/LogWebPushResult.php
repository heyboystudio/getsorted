<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

/**
 * Records how each push went (spec 022): whether the push service accepted it, its status code, and
 * whether the device was gone. Never the endpoint, keys or text, which are credentials and personal.
 */
final class LogWebPushResult
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $report = $event->report;
        $status = $report->getResponse()?->getStatusCode();

        Log::log($report->isSuccess() ? 'info' : 'warning', 'Web push result.', [
            'accepted' => $report->isSuccess(),
            'status' => $status,
            'device_gone' => $report->isSubscriptionExpired(),
        ]);
    }
}
