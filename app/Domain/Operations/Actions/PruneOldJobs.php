<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * POPIA retention for jobs (privacy notice, "How long we keep it"): 24 months after a job ends, its photos and
 * everything the client or pros wrote about it are deleted. A job that never led to an introduction has no
 * money record and is deleted outright. A job that did keeps a bare record (trade, suburb, dates, amounts, the
 * introduction and credit entries) because tax and accounting law needs it for at least 5 years.
 * Safe to run repeatedly.
 */
final class PruneOldJobs
{
    /** @return array{deleted: int, scrubbed: int} */
    public function handle(?CarbonImmutable $now = null): array
    {
        $cutoff = ($now ?? CarbonImmutable::now())->subMonths((int) config('getsorted.jobs.retention_months'));
        $deleted = 0;
        $scrubbed = 0;

        $this->ended($cutoff)->lazyById()->each(function (ServiceJob $job) use (&$deleted, &$scrubbed): void {
            $hasMoneyRecord = DB::table('introductions')->where('service_job_id', $job->id)->exists();

            if (! $hasMoneyRecord) {
                // Estimates, invites and chats go with the job (cascading foreign keys) and photos go with the model.
                // The append-only event log is protected only inside the application, so it is cleared here first.
                DB::transaction(function () use ($job): void {
                    DB::table('service_job_events')->where('service_job_id', $job->id)->delete();
                    $job->delete();
                });
                $deleted++;

                return;
            }

            DB::transaction(function () use ($job): void {
                $job->clearMediaCollection(ServiceJob::PHOTO_COLLECTION);

                DB::table('quote_lines')->whereIn('quote_id', DB::table('quotes')->where('service_job_id', $job->id)->select('id'))->update(['description' => 'Removed']);
                DB::table('quotes')->where('service_job_id', $job->id)->update(['notes' => null, 'withdraw_reason' => null]);
                DB::table('service_job_invites')->where('service_job_id', $job->id)->update(['decline_note' => null]);
                // The event log is append-only for the application; at the end of retention its free text goes.
                DB::table('service_job_events')->where('service_job_id', $job->id)->update(['payload' => '{}']);

                DB::table('service_jobs')->where('id', $job->id)->update([
                    'customer_notes' => null,
                    'ai_summary' => null,
                    'ai_summary_input_hash' => null,
                    'facts' => '[]',
                    'location' => null,
                    'cancel_reason' => null,
                    'scrubbed_at' => now(),
                ]);
            });
            $scrubbed++;
        });

        return ['deleted' => $deleted, 'scrubbed' => $scrubbed];
    }

    /**
     * Finished jobs whose end is older than the cutoff and that have not been scrubbed yet.
     *
     * @return Builder<ServiceJob>
     */
    private function ended(CarbonImmutable $cutoff): Builder
    {
        $ended = array_map(fn (ServiceJobStatus $status): string => $status->value, [...ServiceJobStatus::finished(), ...ServiceJobStatus::ended()]);

        return ServiceJob::query()
            ->whereIn('status', $ended)
            ->whereNull('scrubbed_at')
            ->whereRaw('coalesce(completed_at, cancelled_at, quote_window_ends_at, updated_at) < ?', [$cutoff]);
    }
}
