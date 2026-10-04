<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** A pro opens their invite: marked "seen" the first time (spec 009, AC8). */
final class OpenInvite
{
    public function handle(User $user, ServiceJobInvite $invite): ServiceJobInvite
    {
        Gate::forUser($user)->authorize('view', $invite);

        return DB::transaction(function () use ($invite): ServiceJobInvite {
            $locked = ServiceJobInvite::query()->lockForUpdate()->findOrFail($invite->id);

            if (! $locked->isAvailable()) {
                throw new CannotInvite(__('This job is no longer available.'));
            }

            if ($locked->status === InviteStatus::Invited) {
                $locked->forceFill(['status' => InviteStatus::Viewed, 'viewed_at' => now()])->save();
            }

            return $locked;
        });
    }
}
