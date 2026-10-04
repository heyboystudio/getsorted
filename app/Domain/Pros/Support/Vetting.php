<?php

declare(strict_types=1);

namespace App\Domain\Pros\Support;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\Pro;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Shared guard for vetting actions: right role, not their own application, pro locked (spec 008, AC7, rules). */
final class Vetting
{
    /**
     * @template T
     *
     * @param  list<ProStatus>  $allowed  statuses the action applies to
     * @param  Closure(Pro): T  $change
     * @return T
     */
    public static function locked(User $admin, Pro $pro, array $allowed, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize('vet', $pro);

        return DB::transaction(function () use ($admin, $pro, $allowed, $change): mixed {
            $locked = Pro::query()->lockForUpdate()->findOrFail($pro->id);
            Gate::forUser($admin)->authorize('vet', $locked);

            if (! in_array($locked->status, $allowed, true)) {
                throw new CannotChangeApplication(__('This application is :status, so it cannot be changed that way. Reload to see its latest state.', ['status' => mb_strtolower($locked->status->label())]));
            }

            return $change($locked);
        });
    }

    /** A reason the pro will read; required for rejections, change requests and suspensions. */
    public static function reason(?string $reason): string
    {
        $reason = trim((string) $reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => __('Give a reason the pro will see.'),
        ])->validate();

        return $reason;
    }

    /**
     * Activity log entry without personal data or document contents (AC15).
     *
     * @param  array<string, string|int>  $properties
     */
    public static function log(User $admin, Pro $pro, string $event, array $properties = []): void
    {
        activity()->causedBy($admin)->performedOn($pro)->withProperties($properties)->log($event);
    }
}
