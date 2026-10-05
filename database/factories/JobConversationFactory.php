<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobConversation;
use App\Models\Pro;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobConversation>
 */
class JobConversationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_job_id' => ServiceJob::factory()->open(),
            'pro_id' => Pro::factory()->approved(),
            'status' => 'open',
            'last_message_at' => now(),
        ];
    }
}
