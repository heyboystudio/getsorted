<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run on a new production database before go-live: it must start empty of testing. Lists anything that looks
 * like test data and fails if there is some, so a test dump is never carried over by mistake.
 */
final class CheckForTestDataCommand extends Command
{
    protected $signature = 'getsorted:check-for-test-data';

    protected $description = 'Fail if the database holds anything that looks like test data';

    public function handle(): int
    {
        $problems = [];

        $testUsers = DB::table('users')->where(fn ($query) => $query
            ->where('email', 'like', '%@example.%')->orWhere('email', 'like', 'test.%@usesorted.co.za')->orWhere('email', 'like', '%@test.%'))->count();
        if ($testUsers > 0) {
            $problems[] = "{$testUsers} account(s) with test-looking email addresses";
        }

        $fakePayments = DB::table('credit_purchases')->where('provider_reference', 'like', 'fake%')->count();
        if ($fakePayments > 0) {
            $problems[] = "{$fakePayments} credit purchase(s) made through the fake payment gateway";
        }

        $cap = (int) config('getsorted.ops.max_jobs_before_launch', 0);
        $jobs = DB::table('service_jobs')->count();
        if ($jobs > $cap) {
            $problems[] = "{$jobs} job(s) exist but a new production database should have {$cap}";
        }

        if ($problems === []) {
            $this->info('No test data found.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
