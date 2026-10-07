<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * "Start a job" is the only way to be taken to Siya automatically after signing up or in (decision 056).
 * The home page and /start set a short-lived marker; every other sign-in lands on the account home.
 */
final class BookingStart
{
    private const string KEY = 'booking.start_until';

    private const int MINUTES = 30;

    public static function begin(): void
    {
        session()->put(self::KEY, now()->addMinutes(self::MINUTES)->getTimestamp());
        // A page remembered from an earlier visit (for example the admin panel) must not win over Siya.
        session()->forget('url.intended');
    }

    public static function pending(): bool
    {
        return (int) session(self::KEY, 0) > now()->getTimestamp();
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** Where to send a user who has just signed in, signed up or finished verifying (an intended page still wins). */
    public static function landing(User $user): string
    {
        if (self::pending() && $user->homeRoute() === 'account.home') {
            return route('book');
        }

        return route($user->homeRoute());
    }
}
