<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Data;

use App\Domain\ServiceJobs\Enums\TimeWindow;
use Carbon\CarbonImmutable;

/** What the booking wizard has collected so far; all fields optional until posting. */
final readonly class BookingData
{
    /**
     * @param  array<string, array{prompt: string, type: string, answer: string|int|list<string>}>  $answers  checked answers keyed by question key
     */
    public function __construct(
        public array $answers = [],
        public ?string $notes = null,
        public ?string $propertyPublicId = null,
        public ?CarbonImmutable $preferredDate = null,
        public ?TimeWindow $timeWindow = null,
    ) {}
}
