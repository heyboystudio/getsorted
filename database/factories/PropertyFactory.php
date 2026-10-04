<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Properties\Enums\PropertyType;
use App\Models\Property;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
final class PropertyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'label' => fake()->randomElement(['Home', 'Flat', 'Office', 'Granny flat']),
            'street_address' => fake()->streetAddress(),
            'suburb_id' => Suburb::factory(),
            'location' => null,
            'postal_code' => fake()->numerify('4###'),
            'property_type' => PropertyType::House,
        ];
    }
}
