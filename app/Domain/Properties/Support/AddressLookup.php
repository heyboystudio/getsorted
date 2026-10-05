<?php

declare(strict_types=1);

namespace App\Domain\Properties\Support;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Geocoder;
use App\Models\GeocoderUsage;
use App\Settings\PlacesSettings;
use App\Support\LocalTime;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The one way forms talk to the address provider (spec 015): rate limits per
 * visitor, a daily cap on search sessions, usage rows without address text, and
 * null whenever the form should fall back to manual entry (AC8, AC10, AC11).
 */
final readonly class AddressLookup
{
    public const int MIN_QUERY_LENGTH = 3;

    private const int AUTOCOMPLETE_PER_MINUTE = 30;

    private const int RESOLVE_PER_MINUTE = 10;

    public function __construct(private PlacesSettings $settings) {}

    /** @return list<AddressSuggestion>|null null means "use manual entry" */
    public function suggest(string $query, string $sessionToken, string $visitorKey): ?array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH || mb_strlen($query) > 200) {
            return [];
        }

        if (! $this->allow('autocomplete', $visitorKey, self::AUTOCOMPLETE_PER_MINUTE) || ! $this->reserveSession($sessionToken)) {
            return null;
        }

        /** @var list<AddressSuggestion>|null */
        return $this->call('autocomplete', fn (Geocoder $geocoder): array => $geocoder->autocomplete($query, $sessionToken));
    }

    /** Null when the place can't be resolved or the provider is unavailable. */
    public function resolve(string $placeId, string $sessionToken, string $visitorKey): ?GeocodedAddress
    {
        if (! $this->allow('resolve', $visitorKey, self::RESOLVE_PER_MINUTE)) {
            return null;
        }

        /** @var GeocodedAddress|null */
        return $this->call('resolve', fn (Geocoder $geocoder): ?GeocodedAddress => $geocoder->resolve($placeId, $sessionToken));
    }

    private function allow(string $purpose, string $visitorKey, int $perMinute): bool
    {
        $key = 'places:'.$purpose.':'.hash('sha256', $visitorKey);

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $this->recordThrottled($purpose);

            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    /** Each new session token counts once against today's cap (Durban time). */
    private function reserveSession(string $sessionToken): bool
    {
        if (Cache::has('places:session:'.$sessionToken)) {
            return true;
        }

        $budgetKey = 'places:sessions:'.LocalTime::today()->toDateString();
        Cache::add($budgetKey, 0, now()->addDay());

        if ((int) Cache::increment($budgetKey) > $this->settings->daily_session_cap) {
            Cache::decrement($budgetKey);
            $this->recordThrottled('autocomplete');

            return false;
        }

        Cache::put('places:session:'.$sessionToken, true, now()->addHour());

        return true;
    }

    /**
     * @template T
     *
     * @param  Closure(Geocoder): T  $request
     * @return T|null
     */
    private function call(string $purpose, Closure $request): mixed
    {
        $started = hrtime(true);

        try {
            $result = $request(app(Geocoder::class));
        } catch (Throwable $exception) {
            // The class only: messages could echo the typed address.
            Log::warning('Address lookup failed.', ['exception' => $exception::class]);
            $this->record($purpose, 'error', $started);

            return null;
        }

        $this->record($purpose, 'ok', $started);

        return $result;
    }

    /** At most one throttled row per purpose per minute. */
    private function recordThrottled(string $purpose): void
    {
        if (Cache::add('places:throttled-logged:'.$purpose, true, 60)) {
            GeocoderUsage::query()->create(['purpose' => $purpose, 'outcome' => 'throttled', 'latency_ms' => 0]);
        }
    }

    private function record(string $purpose, string $outcome, int|float $started): void
    {
        GeocoderUsage::query()->create([
            'purpose' => $purpose,
            'outcome' => $outcome,
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ]);
    }
}
