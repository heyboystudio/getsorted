<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Geocoder;

/**
 * Offline geocoder with a few fixed Durban addresses, enough for local
 * development and tests. Unknown queries return no suggestions.
 */
final class FakeGeocoder implements Geocoder
{
    /** @var array<string, GeocodedAddress> keyed by place id */
    private array $places;

    public function __construct()
    {
        $this->places = [
            'fake-umhlanga' => new GeocodedAddress('1 Lighthouse Road, Umhlanga Rocks, Durban', 'Umhlanga', -29.7270, 31.0877),
            'fake-berea' => new GeocodedAddress('10 Musgrave Road, Berea, Durban', 'Berea', -29.8460, 31.0050),
            'fake-westville' => new GeocodedAddress('5 Jan Hofmeyr Road, Westville, Durban', 'Westville', -29.8330, 30.9300),
        ];
    }

    public function autocomplete(string $query, string $sessionToken): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $matches = array_filter(
            $this->places,
            fn (GeocodedAddress $address): bool => str_contains(mb_strtolower($address->formattedAddress), $query),
        );

        return array_map(
            fn (string $placeId): AddressSuggestion => new AddressSuggestion($placeId, $this->places[$placeId]->formattedAddress),
            array_keys($matches),
        );
    }

    public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress
    {
        return $this->places[$placeId] ?? null;
    }
}
