<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\EligibleProsQuery;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Jobs\SendInviteMessage;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Settings\MatchingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Invites the next wave of eligible pros to an open job (spec 009, AC1–AC3,
 * AC6). Eligibility is checked again now; nobody is invited twice.
 */
final readonly class RunInviteWave
{
    public function __construct(private EligibleProsQuery $eligiblePros, private MatchingSettings $settings) {}

    /** @return int how many pros were invited */
    public function handle(ServiceJob $job, bool $firstWave = false): int
    {
        $invites = DB::transaction(function () use ($job, $firstWave): array {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($locked->status !== ServiceJobStatus::Open || $locked->matching_stopped_at !== null) {
                return [];
            }

            $previousWave = (int) $locked->invites()->max('wave');

            // The first wave runs once, even if the queued step is retried.
            if ($firstWave && $previousWave > 0) {
                return [];
            }

            $size = $firstWave ? $this->settings->wave_one_size : $this->settings->later_wave_size;
            $created = [];

            foreach ($this->eligiblePros->rankedFor($locked, $size) as $pro) {
                $invitedAt = now();
                $invite = new ServiceJobInvite;
                $invite->forceFill([
                    'service_job_id' => $locked->id,
                    'pro_id' => $pro->id,
                    'wave' => $previousWave + 1,
                    'invited_at' => $invitedAt,
                    'expires_at' => $invitedAt->copy()->addHours($this->settings->invite_expiry_hours),
                ])->save();
                $created[] = $invite;
            }

            // A wave that found nobody still counts, so the scheduler waits before looking again.
            $locked->forceFill(['last_wave_at' => now()])->save();

            return $created;
        });

        foreach ($invites as $invite) {
            SendInviteMessage::dispatch($invite->id);
        }

        return count($invites);
    }
}
