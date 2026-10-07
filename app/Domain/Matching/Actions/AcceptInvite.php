<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Domain\Quotes\Support\QuoteFlow;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** A pro taps "Accept job": they can do it, so the estimate form opens. Nothing is booked until the client chooses a quote. */
final class AcceptInvite
{
    public function handle(User $user, ServiceJobInvite $invite): ServiceJobInvite
    {
        Gate::forUser($user)->authorize('view', $invite);

        return DB::transaction(function () use ($invite): ServiceJobInvite {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($invite->service_job_id);
            $locked = ServiceJobInvite::query()->lockForUpdate()->findOrFail($invite->id);

            if (! $locked->isAvailable() || $job->status->value !== 'open') {
                throw new CannotInvite(__('This job is no longer available.'));
            }

            if ($job->quotes_count >= QuoteFlow::maxQuotes()) {
                throw new CannotInvite(__('This job already has all the quotes it accepts.'));
            }

            $locked->forceFill(['status' => InviteStatus::Accepted, 'viewed_at' => $locked->viewed_at ?? now()])->save();

            return $locked;
        });
    }
}
