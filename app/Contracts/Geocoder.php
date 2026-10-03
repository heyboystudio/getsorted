<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;

/** Address autocomplete and coordinates (Google Places planned). */
interface Geocoder
{
    /** @return list<AddressSuggestion> */
    public function autocomplete(string $query, string $sessionToken): array;

    public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress;
}
