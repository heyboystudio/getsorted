<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pauses or resumes new invites for an approved pro (spec 021, AC21). Pausing changes
 * nothing about their approval, open invites, quotes or booked jobs; matching just skips
 * them. Repeating the same choice does nothing.
 */
final class SetProAvailability
{
    public function handle(User $user, Pro $pro, bool $paused): void
    {
        abort_unless($pro->user_id === $user->id && $pro->status === ProStatus::Approved, 403);

        DB::transaction(function () use ($user, $pro, $paused): void {
            $locked = Pro::query()->lockForUpdate()->findOrFail($pro->id);

            if ($locked->isPaused() === $paused) {
                return;
            }

            $locked->forceFill(['paused_at' => $paused ? now() : null])->save();
            activity()->performedOn($locked)->causedBy($user)->log($paused ? 'pro paused' : 'pro resumed');
        });
    }
}
