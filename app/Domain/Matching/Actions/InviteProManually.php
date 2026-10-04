<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Jobs\SendInviteMessage;
use App\Models\Pro;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use App\Settings\MatchingSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** An admin invites one more eligible pro to an open job; logged (spec 009, AC11). */
final readonly class InviteProManually
{
    public function __construct(private EligibleProsQuery $eligiblePros, private MatchingSettings $settings) {}

    public function handle(User $admin, ServiceJob $job, Pro $pro): ServiceJobInvite
    {
        Gate::forUser($admin)->authorize('manageMatching', $job);

        $invite = DB::transaction(function () use ($admin, $job, $pro): ServiceJobInvite {
            $locked = ServiceJob::query()->with(['service.trade', 'property.suburb', 'customer'])->lockForUpdate()->findOrFail($job->id);

            if ($locked->status !== ServiceJobStatus::Open) {
                throw new CannotInvite(__('Only open jobs can get more invites.'));
            }

            if ($locked->invites()->where('pro_id', $pro->id)->exists()) {
                throw new CannotInvite(__('This pro was already invited.'));
            }

            if (! $this->eligiblePros->for($locked->service, $locked->property->suburb, $locked->customer)->whereKey($pro->id)->exists()) {
                throw new CannotInvite(__('This pro cannot take this job (service, suburb, registration or status).'));
            }

            $invitedAt = now();
            $invite = new ServiceJobInvite;
            $invite->forceFill([
                'service_job_id' => $locked->id,
                'pro_id' => $pro->id,
                'wave' => max(1, (int) $locked->invites()->max('wave')),
                'invited_at' => $invitedAt,
                'expires_at' => $invitedAt->copy()->addHours($this->settings->invite_expiry_hours),
                'invited_by' => $admin->id,
            ])->save();

            activity()->causedBy($admin)->performedOn($locked)->withProperties(['pro' => $pro->public_id])->log('job_pro_invited');

            return $invite;
        });

        SendInviteMessage::dispatch($invite->id);

        return $invite;
    }
}
