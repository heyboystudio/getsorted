<?php

declare(strict_types=1);

namespace App\Domain\Pros\Data;

use App\Domain\Pros\Enums\BusinessType;

final readonly class BusinessDetails
{
    public function __construct(
        public string $businessName,
        public BusinessType $businessType,
        public ?string $vatNumber,
    ) {}
}
