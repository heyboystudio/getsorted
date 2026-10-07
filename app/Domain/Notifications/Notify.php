<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use App\Notifications\UserNotice;

/**
 * The one way the app tells a person something: an in-app notice, a pop-up on their devices (spec 022) and
 * an email when it matters. `$group` is the customer preference group that can switch the pop-up off.
 */
final class Notify
{
    public static function user(User $user, string $kind, string $title, string $body, string $url, bool $email = false, ?string $group = null): void
    {
        $user->notify(new UserNotice($kind, $title, $body, $url, $email, $group));
    }
}
