<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class GeocodedAddress
{
    /** @param  list<string>  $areaNames */
    public function __construct(
        public string $formattedAddress,
        public ?string $suburb,
        public float $latitude,
        public float $longitude,
        /** Street number and route, e.g. "10 Musgrave Road"; null when the place has none. */
        public ?string $streetLine = null,
        public ?string $postalCode = null,
        /** Other area names Google gives (locality, sublocality levels), most specific first. */
        public array $areaNames = [],
        /** Google's district, e.g. "eThekwini Metropolitan Municipality". */
        public ?string $municipality = null,
    ) {}
}
