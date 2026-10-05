<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\AddressSuggestion;
use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Exceptions\GeocoderUnavailable;

/** Address autocomplete and coordinates (Google Places, spec 015). */
interface Geocoder
{
    /**
     * @return list<AddressSuggestion>
     *
     * @throws GeocoderUnavailable
     */
    public function autocomplete(string $query, string $sessionToken): array;

    /** @throws GeocoderUnavailable */
    public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress;
}
