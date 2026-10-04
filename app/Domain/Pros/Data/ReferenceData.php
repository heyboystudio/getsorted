<?php

declare(strict_types=1);

namespace App\Domain\Pros\Data;

final readonly class ReferenceData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $relationship,
    ) {}
}
