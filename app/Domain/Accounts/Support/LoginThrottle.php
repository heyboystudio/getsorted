<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP rate limits from the security baseline §1. Keys use the normalised
 * number (so formatting tricks don't bypass the limit), hashed so phone
 * numbers never sit in the cache table in plain text.
 */
final class LoginThrottle
{
    /** @return list<Limit> */
    public static function sendLimits(string $phoneE164, ?string $ip): array
    {
        return [
            Limit::perMinutes(15, 3)->by('otp-send:phone:'.hash('sha256', $phoneE164)),
            Limit::perHour(10)->by('otp-send:ip:'.$ip),
        ];
    }

    public static function verifyLimit(?string $ip): Limit
    {
        return Limit::perMinutes(15, 10)->by('otp-verify:ip:'.$ip);
    }

    /**
     * Records one attempt against every limit, unless one is already used up.
     *
     * @param  list<Limit>  $limits
     * @return int|null seconds until allowed again, or null if the attempt is allowed
     */
    public static function attempt(array $limits): ?int
    {
        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                return max(1, RateLimiter::availableIn($limit->key));
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return null;
    }
}
