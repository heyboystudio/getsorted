<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pro;
use App\Models\User;
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
            'status' => 'applied',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'approved_at' => now()]);
    }
}
