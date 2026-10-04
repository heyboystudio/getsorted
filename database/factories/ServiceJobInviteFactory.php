<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pro;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJobInvite>
 */
class ServiceJobInviteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_job_id' => ServiceJob::factory()->open(),
            'pro_id' => Pro::factory()->approved(),
            'wave' => 1,
            'status' => 'invited',
            'invited_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }
}
