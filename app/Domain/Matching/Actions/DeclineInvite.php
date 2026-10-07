<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** A pro turns an invite down with a reason; it cannot be reopened (spec 009, AC9). */
final class DeclineInvite
{
    public function handle(User $user, ServiceJobInvite $invite, DeclineReason $reason, ?string $note): void
    {
        Gate::forUser($user)->authorize('view', $invite);
        $note = $note === null ? null : trim($note);

        Validator::make(['note' => $note], [
            'note' => [$reason === DeclineReason::Other ? 'required' : 'nullable', 'string', 'max:300'],
        ], ['note.required' => __('Tell us briefly why.')])->validate();

        $limitKey = 'invite-declines:'.$user->id;

        if (RateLimiter::tooManyAttempts($limitKey, (int) config('getsorted.matching.declines_per_hour'))) {
            throw ValidationException::withMessages(['reason' => __('Please try again later.')]);
        }

        RateLimiter::hit($limitKey, 3600);

        DB::transaction(function () use ($invite, $reason, $note): void {
            $locked = ServiceJobInvite::query()->lockForUpdate()->findOrFail($invite->id);

            if (! $locked->isAvailable()) {
                throw new CannotInvite(__('This job is no longer available.'));
            }

            $locked->forceFill([
                'status' => InviteStatus::Declined,
                'decline_reason' => $reason,
                'decline_note' => $note === '' ? null : $note,
                'responded_at' => now(),
            ])->save();
        });
    }
}
