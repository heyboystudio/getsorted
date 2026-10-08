<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Notify;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Support\BookedJob;
use App\Models\ServiceJob;
use App\Support\LocalTime;
use Illuminate\Console\Command;

/** Spec 024, AC8: three days after the start date, ask both sides once whether the job is done. */
final class NudgeFinishedJobsCommand extends Command
{
    protected $signature = 'getsorted:nudge-finished-jobs';

    protected $description = 'Ask client and pro whether booked jobs past their start date are done';

    public function handle(): int
    {
        $count = 0;

        ServiceJob::query()
            ->with(['customer', 'trade'])
            ->whereIn('status', array_map(fn (ServiceJobStatus $status): string => $status->value, BookedJob::OPEN_STATUSES))
            ->whereNull('finish_nudged_at')
            ->whereDate('scheduled_for', '<=', LocalTime::today()->subDays(3)->toDateString())
            ->each(function (ServiceJob $job) use (&$count): void {
                $trade = mb_strtolower($job->trade->name);
                $pro = BookedJob::pro($job);

                Notify::user($job->customer, 'job_finish_check', __('Is your :trade job done?', ['trade' => $trade]), __('Open the job and tap "Mark as done" when the work is finished.'), route('jobs.show', $job), email: true);

                if ($pro !== null) {
                    Notify::user($pro->user, 'job_finish_check', __('Is the :trade job done?', ['trade' => $trade]), __('Open the job and tap "Mark as done" when the work is finished.'), route('pros.jobs'), email: true);
                }

                $job->forceFill(['finish_nudged_at' => now()])->save();
                $count++;
            });

        $this->info("Asked about {$count} job(s).");

        return self::SUCCESS;
    }
}
