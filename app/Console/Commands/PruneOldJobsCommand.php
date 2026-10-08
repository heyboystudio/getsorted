<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Actions\PruneOldJobs;
use Illuminate\Console\Command;

/** Deletes job photos and personal content 24 months after a job ends (privacy notice, retention). */
final class PruneOldJobsCommand extends Command
{
    protected $signature = 'getsorted:prune-old-jobs';

    protected $description = 'Delete or scrub finished jobs older than the retention period';

    public function handle(PruneOldJobs $prune): int
    {
        $result = $prune->handle();
        $this->info("Deleted {$result['deleted']} job(s) with no money record; scrubbed {$result['scrubbed']} job(s) that keep an accounting record.");

        return self::SUCCESS;
    }
}
