<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use App\Contracts\Data\MessageChannel;
use App\Models\User;

/**
 * A customer's choices about text messages (spec 021, AC15): which kinds they get, and by
 * WhatsApp or SMS. Everything is on by default so nobody loses a message on release. Login
 * codes and other security messages never go through these choices.
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

    public static function channel(User $user): MessageChannel
    {
        return MessageChannel::tryFrom((string) ($user->notification_preferences['channel'] ?? '')) ?? MessageChannel::WhatsApp;
    }

    /** @param array<string, bool> $groups */
    public static function save(User $user, array $groups, MessageChannel $channel): void
    {
        $known = [];
        foreach (array_keys(self::groups()) as $group) {
            $known[$group] = (bool) ($groups[$group] ?? true);
        }

        $user->forceFill(['notification_preferences' => ['groups' => $known, 'channel' => $channel->value]])->save();
        activity()->performedOn($user)->causedBy($user)->withProperties(['groups' => $known, 'channel' => $channel->value])->log('notification preferences changed');
    }
}
