<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pro;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pro>
 */
class ProFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->pro(),
            'business_name' => fake()->company(),
            'status' => 'draft',
            'base_location' => Point::makeGeodetic(-29.8587, 31.0218),
            'base_area_label' => 'Musgrave',
            'service_radius_km' => 15,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'approved_at' => now()]);
    }
}
