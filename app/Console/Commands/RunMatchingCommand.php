<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Matching\Actions\RunMatchingSchedule;
use Illuminate\Console\Command;

/** Spec 009: expire invites, close finished jobs' invites, send due waves. Safe to run repeatedly. */
final class RunMatchingCommand extends Command
{
    protected $signature = 'sortd:run-matching';

    protected $description = 'Expire invites and send due invite waves';

    public function handle(RunMatchingSchedule $schedule): int
    {
        $schedule->handle();

        return self::SUCCESS;
    }
}
