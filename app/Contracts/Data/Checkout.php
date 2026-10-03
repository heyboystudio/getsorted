<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class Checkout
{
    public function __construct(
        public string $providerReference,
        public string $redirectUrl,
    ) {}
}
