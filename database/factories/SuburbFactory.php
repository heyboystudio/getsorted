<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Properties\Enums\Region;
use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Suburb> */
final class SuburbFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->lexify('suburb_??????'),
            'name' => fake()->unique()->city(),
            'region' => Region::BereaCentral,
            'municipality' => 'eThekwini',
            'centroid' => Point::makeGeodetic(fake()->randomFloat(4, -29.95, -29.75), fake()->randomFloat(4, 30.85, 31.08)),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
