<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
final class ServiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'key' => fake()->unique()->lexify('service_??????'),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'requires_registration' => null,
            'emergency_capable' => false,
            'safety_advice' => [],
            'is_active' => true,
            'sort' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
