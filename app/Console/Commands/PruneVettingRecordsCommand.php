<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pros\Actions\PruneVettingRecords;
use Illuminate\Console\Command;

/** POPIA retention for vetting records (spec 008, AC14). Safe to run repeatedly. */
final class PruneVettingRecordsCommand extends Command
{
    protected $signature = 'sortd:prune-vetting-records';

    protected $description = 'Delete documents and references of rejected or abandoned pro applications after the retention period';

    public function handle(PruneVettingRecords $prune): int
    {
        $count = $prune->handle();

        $this->info("Pruned vetting records for {$count} applications.");

        return self::SUCCESS;
    }
}
