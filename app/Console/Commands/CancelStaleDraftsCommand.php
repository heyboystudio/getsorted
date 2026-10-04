<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\ServiceJobs\Actions\CancelServiceJob;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Settings\JobTimers;
use Illuminate\Console\Command;

/** Cancels drafts untouched for the configured number of days (spec 005, AC12). Safe to run repeatedly. */
final class CancelStaleDraftsCommand extends Command
{
    protected $signature = 'sortd:cancel-stale-drafts';

    protected $description = 'Cancel booking drafts that have not been touched for the draft expiry period';

    public function handle(CancelServiceJob $cancel, JobTimers $timers): int
    {
        $cutoff = now()->subDays($timers->draft_expiry_days);
        $count = 0;

        ServiceJob::query()
            ->where('status', ServiceJobStatus::Draft)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (ServiceJob $job) use ($cancel, &$count): void {
                if ($cancel->handle($job, ActorType::System, null, 'Draft expired') instanceof ServiceJob) {
                    $count++;
                }
            });

        $this->info("Cancelled {$count} stale drafts.");

        return self::SUCCESS;
    }
}
