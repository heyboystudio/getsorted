<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class GeocodedAddress
{
    public function __construct(
        public string $formattedAddress,
        public ?string $suburb,
        public float $latitude,
        public float $longitude,
    ) {}
}
