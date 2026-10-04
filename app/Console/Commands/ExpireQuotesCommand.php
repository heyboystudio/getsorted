<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Quotes\Actions\ExpireQuotesAndJobs;
use Illuminate\Console\Command;

/** Spec 010: expire old quotes and jobs whose quote window ended. Safe to run repeatedly. */
final class ExpireQuotesCommand extends Command
{
    protected $signature = 'sortd:expire-quotes';

    protected $description = 'Expire quotes past their validity and open jobs past their quote window';

    public function handle(ExpireQuotesAndJobs $expire): int
    {
        $expire->handle();

        return self::SUCCESS;
    }
}
