<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceJob> */
final class ServiceJobFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'trade_id' => Trade::factory(),
            'property_id' => null,
            'preferred_date' => now()->addDays(3)->toDateString(),
            'time_window' => TimeWindow::Morning,
            'facts' => [],
            'customer_notes' => null,
        ];
    }

    /** A posted job (states are set directly only in factories, for test setup). */
    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ServiceJobStatus::Open,
            'posted_at' => now(),
            'quote_window_ends_at' => now()->addHours(72),
        ])->afterMaking(function (ServiceJob $job): void {
            if ($job->property_id === null) {
                $job->property()->associate(Property::factory()->for(User::query()->findOrFail($job->customer_id))->create());
            }
        });
    }

    public function forProperty(Property $property): static
    {
        return $this->state(fn (array $attributes): array => ['customer_id' => $property->user_id, 'property_id' => $property->id]);
    }
}
