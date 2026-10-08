<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiUsage;
use App\Models\ServiceJob;
use App\Models\WaitlistEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clears the activity that testing leaves behind on a test or staging site (jobs, estimates, chats, introductions,
 * reviews, notices, waiting list, AI usage) and keeps the accounts, pros and saved addresses so testing can carry on.
 * Real payments are kept: credit purchases and the credit bought stay in the ledger. Never runs in production.
 */
final class PurgeTestDataCommand extends Command
{
    protected $signature = 'getsorted:purge-test-data {--force : Do it. Without this the command only shows what it would remove}';

    protected $description = 'Remove test jobs and the activity around them from a test site, keeping accounts';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command never runs in production.');

            return self::FAILURE;
        }

        $counts = [
            'jobs' => ServiceJob::query()->count(),
            'estimates' => DB::table('quotes')->count(),
            'introductions' => DB::table('introductions')->count(),
            'reviews' => DB::table('reviews')->count(),
            'notices' => DB::table('notifications')->count(),
            'waiting-list entries' => WaitlistEntry::query()->count(),
            'AI usage rows' => AiUsage::query()->count(),
        ];

        foreach ($counts as $what => $count) {
            $this->line(sprintf('%-22s %d', $what, $count));
        }

        if (! $this->option('force')) {
            $this->warn('Dry run. Add --force to remove these. Accounts, pros, saved addresses and purchased credit are kept.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            // Fees taken for introductions go with the introductions; credit that was bought stays.
            DB::table('pro_credit_entries')->where('type', 'introduction')->delete();
            DB::table('reviews')->delete();
            DB::table('introductions')->delete();
            DB::table('service_job_events')->delete();
            DB::table('pro_job_allocations')->delete();
            // Estimates, invites, chats and messages go with their job; photos go with the model.
            ServiceJob::query()->lazyById()->each(fn (ServiceJob $job) => $job->delete());
            DB::table('notifications')->delete();
            WaitlistEntry::query()->delete();
            AiUsage::query()->delete();
        });

        $this->info('Test jobs and activity removed. Accounts, pros, saved addresses and purchased credit were kept.');

        return self::SUCCESS;
    }
}
