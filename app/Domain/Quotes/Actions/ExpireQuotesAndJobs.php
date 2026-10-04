<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Support\QuoteFlow;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Jobs\SendJobExpiredMessage;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;

/**
 * Quotes past their valid-until date expire; open jobs past their quote window
 * with no accepted quote expire (spec 010, AC11, AC12). Safe to run repeatedly.
 */
final readonly class ExpireQuotesAndJobs
{
    public function __construct(private ServiceJobStateMachine $stateMachine) {}

    public function handle(): void
    {
        $jobIds = Quote::query()->where('status', QuoteStatus::Submitted)->where('valid_until', '<', LocalTime::today()->toDateString())
            ->distinct()->pluck('service_job_id');

        foreach ($jobIds as $jobId) {
            DB::transaction(function () use ($jobId): void {
                $job = ServiceJob::query()->lockForUpdate()->findOrFail($jobId);
                $job->quotes()->where('status', QuoteStatus::Submitted)->where('valid_until', '<', LocalTime::today()->toDateString())
                    ->update(['status' => QuoteStatus::Expired->value, 'updated_at' => now()]);
                QuoteFlow::refreshCount($job);
            });
        }

        ServiceJob::query()->where('status', ServiceJobStatus::Open)->where('quote_window_ends_at', '<=', now())
            ->lazyById()
            ->each(function (ServiceJob $candidate): void {
                $expired = DB::transaction(function () use ($candidate): bool {
                    $job = ServiceJob::query()->lockForUpdate()->findOrFail($candidate->id);

                    if ($job->status !== ServiceJobStatus::Open || $job->quote_window_ends_at === null || $job->quote_window_ends_at->isFuture()) {
                        return false;
                    }

                    $job->quotes()->where('status', QuoteStatus::Submitted)->update(['status' => QuoteStatus::Expired->value, 'updated_at' => now()]);
                    $job->invites()->whereIn('status', InviteStatus::open())->update(['status' => InviteStatus::Closed->value, 'responded_at' => now(), 'updated_at' => now()]);
                    $job->forceFill(['quotes_count' => 0]);
                    $this->stateMachine->transition($job, ServiceJobStatus::Expired, 'job_expired', ActorType::System, null);

                    return true;
                });

                if ($expired) {
                    SendJobExpiredMessage::dispatch($candidate->id);
                }
            });
    }
}
