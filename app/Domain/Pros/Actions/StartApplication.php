<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Enums\ReferenceOutcome;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Domain\Pros\ProStatusMachine;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Opens (or reopens after the reapply wait) a pro's single application (spec 008, AC1, AC10). */
final readonly class StartApplication
{
    public function __construct(private ProStatusMachine $statuses) {}

    public function handle(User $user): Pro
    {
        Gate::forUser($user)->authorize('create', Pro::class);

        return DB::transaction(function () use ($user): Pro {
            $pro = Pro::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if (! $pro instanceof Pro) {
                $pro = new Pro;
                $pro->user()->associate($user);
                $pro->forceFill(['status' => ProStatus::Draft, 'last_activity_at' => now()]);

                try {
                    // Two tabs opening the form at once: the second one uses the first one's draft.
                    DB::transaction(fn () => $pro->save());
                } catch (UniqueConstraintViolationException) {
                    return Pro::query()->where('user_id', $user->id)->firstOrFail();
                }

                return $pro;
            }

            if ($pro->status === ProStatus::Rejected) {
                if ($pro->reapply_after !== null && $pro->reapply_after->isFuture()) {
                    throw new CannotChangeApplication(__('You can apply again from :date.', ['date' => $pro->reapply_after->format('j F Y')]));
                }

                $pro->forceFill(['submitted_at' => null, 'decision_reason' => null, 'reapply_after' => null, 'last_activity_at' => now()]);
                $this->statuses->transition($pro, ProStatus::Draft, $user);

                // A new round starts from scratch: nothing verified last time counts now (security review).
                $pro->documents()->update(['status' => DocumentStatus::Pending->value, 'verified_at' => null, 'verified_by' => null, 'expires_at' => null, 'flag_message' => null, 'notes' => null]);
                $pro->references()->update(['outcome' => ReferenceOutcome::Pending->value, 'note' => null, 'checked_by' => null, 'checked_at' => null]);
            }

            return $pro;
        });
    }
}
