<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogue\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Trade> */
final class TradeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('trade_??????'),
            'name' => fake()->words(2, true),
            'status' => TradeStatus::Demo,
            'is_active' => true,
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}
