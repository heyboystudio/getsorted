<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'phone_e164' => '+2782'.fake()->unique()->numerify('#######'),
            'suburb_text' => 'Musgrave',
            'suburb_key' => 'musgrave',
            'service_id' => Service::factory(),
            'privacy_version' => config('sortd.legal.privacy_version'),
            'consented_at' => now(),
        ];
    }
}
