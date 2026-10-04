<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Domain\Pros\ProStatusMachine;
use App\Models\Pro;
use App\Models\User;
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
                $pro->forceFill(['status' => ProStatus::Draft, 'last_activity_at' => now()])->save();

                return $pro;
            }

            if ($pro->status === ProStatus::Rejected) {
                if ($pro->reapply_after !== null && $pro->reapply_after->isFuture()) {
                    throw new CannotChangeApplication(__('You can apply again from :date.', ['date' => $pro->reapply_after->format('j F Y')]));
                }

                $pro->forceFill(['submitted_at' => null, 'decision_reason' => null, 'reapply_after' => null, 'last_activity_at' => now()]);
                $this->statuses->transition($pro, ProStatus::Draft, $user);
            }

            return $pro;
        });
    }
}
