<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sign-up rate limits (security baseline §1; values in config/getsorted.php).
 */
final class LoginThrottle
{
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
        $config = config('getsorted.auth.'.$name);

        return Limit::perMinutes($config['minutes'], $config['max'])->by($key);
    }
}
