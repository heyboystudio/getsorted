<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP and sign-up rate limits (security baseline §1; values in config/getsorted.php).
 * Phone keys use the normalised number, so formatting tricks don't bypass them,
 * and are HMAC'd with the app key so the cache table never holds a reversible number.
 */
final class LoginThrottle
{
    /** @return list<Limit> */
    public static function sendLimits(string $phoneE164, ?string $ip): array
    {
        $phoneKey = hash_hmac('sha256', $phoneE164, (string) config('app.key'));

        $dailyCap = ! app()->environment('local') || (bool) config('getsorted.otp.daily_cap_in_local');

        return array_values(array_filter([
            self::limit('send_per_phone', 'otp-send:phone:'.$phoneKey),
            $dailyCap ? self::limit('send_per_phone_daily', 'otp-send-daily:phone:'.$phoneKey) : null,
            self::limit('send_per_ip', 'otp-send:ip:'.$ip),
        ]));
    }

    public static function verifyLimit(?string $ip): Limit
    {
        return self::limit('verify_per_ip', 'otp-verify:ip:'.$ip);
    }

    public static function registerLimit(?string $ip): Limit
    {
        return self::limit('register_per_ip', 'register:ip:'.$ip);
    }

    /**
     * Counts one attempt against every limit. The hit is recorded first and the
     * returned count compared, so parallel requests cannot slip past the check.
     *
     * @param  list<Limit>  $limits
     * @return int|null seconds until allowed again, or null if the attempt is allowed
     */
    public static function attempt(array $limits): ?int
    {
        $waitSeconds = null;

        foreach ($limits as $limit) {
            $count = RateLimiter::hit($limit->key, $limit->decaySeconds);

            if ($count > $limit->maxAttempts) {
                $waitSeconds = max($waitSeconds ?? 0, RateLimiter::availableIn($limit->key), 1);
            }
        }

        return $waitSeconds;
    }

    private static function limit(string $name, string $key): Limit
    {
        /** @var array{max: int, minutes: int} $config */
        $config = config('getsorted.otp.'.$name);

        return Limit::perMinutes($config['minutes'], $config['max'])->by($key);
    }
}
