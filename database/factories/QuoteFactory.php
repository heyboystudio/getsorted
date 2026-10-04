<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_job_id' => ServiceJob::factory()->open(),
            'pro_id' => Pro::factory()->approved(),
            'version' => 1,
            'status' => 'submitted',
            'labour_cents' => 45000,
            'materials_cents' => 12050,
            'callout_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 57050,
            'deposit_percent' => 0,
            'deposit_cents' => 0,
            'earliest_start_date' => now()->addDay()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'submitted_at' => now(),
        ];
    }
}
