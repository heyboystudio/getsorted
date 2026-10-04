<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Data;

use App\Domain\Quotes\Enums\LineKind;

final readonly class QuoteLineData
{
    /**
     * @param  string  $quantity  decimal with up to 2 places, e.g. "1.5"
     */
    public function __construct(
        public LineKind $kind,
        public string $description,
        public string $quantity,
        public int $unitPriceCents,
    ) {}
}
