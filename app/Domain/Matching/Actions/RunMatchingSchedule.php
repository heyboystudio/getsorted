<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Settings\MatchingSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * The every-five-minutes matching run (spec 009, AC3–AC5): expire unanswered
 * invites, close invites on jobs that stopped collecting quotes, and send due
 * waves. Safe to run repeatedly.
 */
final readonly class RunMatchingSchedule
{
    public function __construct(private RunInviteWave $runInviteWave, private MatchingSettings $settings) {}

    public function handle(): void
    {
        ServiceJobInvite::query()->whereIn('status', InviteStatus::open())->where('expires_at', '<=', now())
            ->update(['status' => InviteStatus::Expired->value, 'updated_at' => now()]);

        ServiceJobInvite::query()->whereIn('status', InviteStatus::open())
            ->whereHas('serviceJob', fn (Builder $job): Builder => $job->where('status', '!=', ServiceJobStatus::Open))
            ->update(['status' => InviteStatus::Closed->value, 'responded_at' => now(), 'updated_at' => now()]);

        ServiceJob::query()
            ->where('status', ServiceJobStatus::Open)
            ->whereNull('matching_stopped_at')
            ->where(fn (Builder $query): Builder => $query->whereNull('last_wave_at')->orWhere('last_wave_at', '<=', now()->subHours($this->settings->wave_interval_hours)))
            // Current submitted quotes (spec 010) decide whether more pros are needed.
            ->where('quotes_count', '<', $this->settings->enough_quotes)
            ->lazyById()
            ->each(fn (ServiceJob $job): int => $this->runInviteWave->handle($job, firstWave: $job->last_wave_at === null));
    }
}
