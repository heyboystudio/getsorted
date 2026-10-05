<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

/**
 * Google details kept in the server session between Google's callback and the
 * next step, never trusted from the browser (spec 014, AC2). Expire after 15 minutes.
 */
final class GoogleSignIn
{
    private const string SIGN_UP = 'auth.google_sign_up';

    private const string LINK = 'auth.google_link';

    private const int TTL_SECONDS = 900;

    /** @param array{id: string, email: string, first_name: string, last_name: string} $profile */
    public static function rememberSignUp(array $profile): void
    {
        session()->put(self::SIGN_UP, [...$profile, 'at' => now()->getTimestamp()]);
    }

    /** @return array{id: string, email: string, first_name: string, last_name: string}|null */
    public static function pendingSignUp(): ?array
    {
        return self::fresh(self::SIGN_UP);
    }

    public static function forgetSignUp(): void
    {
        session()->forget(self::SIGN_UP);
    }

    /** Google matched an existing password account; link it after the next password sign-in. */
    public static function rememberLink(string $googleId, string $email): void
    {
        session()->put(self::LINK, ['id' => $googleId, 'email' => $email, 'first_name' => '', 'last_name' => '', 'at' => now()->getTimestamp()]);
    }

    /** @return array{id: string, email: string, first_name: string, last_name: string}|null */
    public static function pendingLink(): ?array
    {
        return self::fresh(self::LINK);
    }

    public static function forgetLink(): void
    {
        session()->forget(self::LINK);
    }

    /** @return array{id: string, email: string, first_name: string, last_name: string}|null */
    private static function fresh(string $key): ?array
    {
        $value = session()->get($key);

        if (! is_array($value) || ! isset($value['id'], $value['email'], $value['at']) || now()->getTimestamp() - (int) $value['at'] > self::TTL_SECONDS) {
            return null;
        }

        return [
            'id' => (string) $value['id'],
            'email' => (string) $value['email'],
            'first_name' => (string) ($value['first_name'] ?? ''),
            'last_name' => (string) ($value['last_name'] ?? ''),
        ];
    }
}
