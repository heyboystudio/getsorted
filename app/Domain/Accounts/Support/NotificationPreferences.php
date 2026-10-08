<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use App\Models\User;

/**
 * A customer's choices about notices (spec 021, AC15): which kinds they get by email and
 * pop-up. Everything is on by default so nobody loses a notice on release. Security
 * messages never go through these choices.
 */
final class NotificationPreferences
{
    /** @return array<string, string> group key => label */
    public static function groups(): array
    {
        return [
            'quotes' => __('Quotes: when a pro sends, changes or withdraws a quote'),
            'job_updates' => __('Job updates: when your request is posted or runs out of time'),
            'messages' => __('Messages: when a pro writes to you in a job chat'),
        ];
    }

    public static function allows(User $user, string $group): bool
    {
        $groups = $user->notification_preferences['groups'] ?? [];

        return ! is_array($groups) || ($groups[$group] ?? true) !== false;
    }

    /** @param array<string, bool> $groups */
    public static function save(User $user, array $groups): void
    {
        $known = [];
        foreach (array_keys(self::groups()) as $group) {
            $known[$group] = (bool) ($groups[$group] ?? true);
        }

        $user->forceFill(['notification_preferences' => ['groups' => $known]])->save();
        activity()->performedOn($user)->causedBy($user)->withProperties(['groups' => $known])->log('notification preferences changed');
    }
}
