<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class AddressSuggestion
{
    public function __construct(
        public string $placeId,
        public string $description,
    ) {}
}
