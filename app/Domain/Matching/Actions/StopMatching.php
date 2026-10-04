<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** An admin stops further invite waves for a job; open invites stay valid (spec 009, AC11). */
final class StopMatching
{
    public function handle(User $admin, ServiceJob $job, string $reason): void
    {
        Gate::forUser($admin)->authorize('manageMatching', $job);
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:500']])->validate();

        DB::transaction(function () use ($admin, $job, $reason): void {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            $locked->forceFill(['matching_stopped_at' => $locked->matching_stopped_at ?? now(), 'matching_stopped_reason' => $reason])->save();

            activity()->causedBy($admin)->performedOn($locked)->log('job_matching_stopped');
        });
    }
}
