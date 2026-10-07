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
 * Invites the nearest eligible pros to an open job, up to `invite_count` in total (spec 020).
 * Safe to repeat: it only tops up what is missing, so a pro who joins later can still be invited
 * while the job is open. Eligibility is checked again now; nobody is invited twice.
 */
final readonly class RunInviteWave
{
    public function __construct(private EligibleProsQuery $eligiblePros, private MatchingSettings $settings) {}

    /** @return int how many pros were invited */
    public function handle(ServiceJob $job): int
    {
        $invites = DB::transaction(function () use ($job): array {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($locked->status !== ServiceJobStatus::Open || $locked->matching_stopped_at !== null || $locked->quotes_count >= $this->settings->max_quotes) {
                return [];
            }

            $missing = $this->settings->invite_count - $locked->invites()->count();
            $created = [];

            foreach ($this->eligiblePros->rankedFor($locked, $missing) as $pro) {
                $invitedAt = now();
                $invite = new ServiceJobInvite;
                $invite->forceFill([
                    'service_job_id' => $locked->id,
                    'pro_id' => $pro->id,
                    'wave' => 1,
                    'invited_at' => $invitedAt,
                    'expires_at' => $invitedAt->copy()->addHours($this->settings->invite_expiry_hours),
                ])->save();
                $created[] = $invite;
            }

            // Recorded even when nobody was found, so the schedule waits before looking again.
            $locked->forceFill(['last_wave_at' => now()])->save();

            return $created;
        });

        foreach ($invites as $invite) {
            SendInviteMessage::dispatch($invite->id);
        }

        return count($invites);
    }
}
