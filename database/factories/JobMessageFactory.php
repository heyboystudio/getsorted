<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobConversation;
use App\Models\JobMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobMessage>
 */
class JobMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'job_conversation_id' => JobConversation::factory(),
            'sender_type' => 'customer',
            'body' => fake()->sentence(),
            'kind' => 'text',
        ];
    }
}
