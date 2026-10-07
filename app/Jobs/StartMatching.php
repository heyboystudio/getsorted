<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Matching\Actions\RunInviteWave;
use App\Models\ServiceJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Invites the nearest eligible pros right after a job is posted (spec 009, spec 020). Thin; safe to retry. */
final class StartMatching implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $serviceJobId,
    ) {
        $this->onQueue('matching');
        $this->afterCommit();
    }

    public function handle(RunInviteWave $runInviteWave): void
    {
        $job = ServiceJob::query()->find($this->serviceJobId);

        if ($job instanceof ServiceJob) {
            $runInviteWave->handle($job);
        }
    }
}
