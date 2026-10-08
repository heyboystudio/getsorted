<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;

/** The customer's five-step view of a job's progress (spec 021, AC10). */
final class JobStages
{
    /** @return list<string> */
    public static function labels(): array
    {
        return [__('Posted'), __('Quotes'), __('Booked'), __('Work'), __('Done')];
    }

    /**
     * Each step as done, current or upcoming; null for states with no progress to show
     * (drafts, disputes, cancelled and expired jobs).
     *
     * @return list<array{label: string, state: 'done'|'current'|'upcoming'}>|null
     */
    public static function for(ServiceJobStatus $status): ?array
    {
        $current = match ($status) {
            ServiceJobStatus::Open => 1,
            ServiceJobStatus::AwaitingDeposit, ServiceJobStatus::Scheduled => 2,
            ServiceJobStatus::InProgress => 3,
            ServiceJobStatus::AwaitingFinalPayment => 4,
            ServiceJobStatus::Completed, ServiceJobStatus::Closed => 5,
            default => null,
        };

        if ($current === null) {
            return null;
        }

        return array_map(
            fn (string $label, int $index): array => ['label' => $label, 'state' => $index < $current ? 'done' : ($index === $current ? 'current' : 'upcoming')],
            self::labels(),
            array_keys(self::labels()),
        );
    }
}
