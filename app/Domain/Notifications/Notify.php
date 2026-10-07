<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use App\Notifications\UserNotice;

/** The one way the app tells a person something: an in-app notice, plus an email when it matters. */
final class Notify
{
    public static function user(User $user, string $kind, string $title, string $body, string $url, bool $email = false): void
    {
        $user->notify(new UserNotice($kind, $title, $body, $url, $email));
    }
}
