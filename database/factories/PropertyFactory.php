<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Properties\Enums\PropertyType;
use App\Models\Property;
use Clickbar\Magellan\Data\Geometries\Point;
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
            'location' => Point::makeGeodetic(-29.8587, 31.0218),
            'location_source' => 'places',
            'area_label' => 'Musgrave',
            'postal_code' => fake()->numerify('4###'),
            'property_type' => PropertyType::House,
        ];
    }
}
