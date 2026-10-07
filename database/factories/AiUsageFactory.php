<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Models\AiUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purpose' => AiPurpose::Chat,
            'provider' => 'fake',
            'model' => 'fake-model',
            'input_tokens' => 100,
            'output_tokens' => 20,
            'latency_ms' => 400,
            'outcome' => AiOutcome::Ok,
        ];
    }
}
