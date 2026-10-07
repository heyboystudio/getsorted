<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * An approved pro edits the parts of their profile that need no review (spec 021, AC26): bio,
 * weekly job cap, and service suburbs inside the launch area. Services and registrations go
 * through review instead. Every change is logged.
 */
final class UpdateProProfile
{
    public const int MAX_WEEKLY_CAP = 50;

    public function bio(User $user, Pro $pro, string $bio): void
    {
        $bio = trim($bio);
        Validator::make(['bio' => $bio], ['bio' => ['required', 'string', 'max:500']], [
            'bio.required' => __('Write a short line about your business.'),
        ])->validate();

        $this->change($user, $pro, 'bio', fn (Pro $locked) => $locked->forceFill(['bio' => $bio])->save());
    }

    public function weeklyCap(User $user, Pro $pro, ?int $cap): void
    {
        Validator::make(['cap' => $cap], ['cap' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_WEEKLY_CAP]], [
            'cap.*' => __('Choose a number from 1 to :max, or leave it empty for no limit.', ['max' => self::MAX_WEEKLY_CAP]),
        ])->validate();

        $this->change($user, $pro, 'weekly_job_cap', fn (Pro $locked) => $locked->forceFill(['weekly_job_cap' => $cap])->save(), ['cap' => $cap]);
    }

    /** @param  list<int>  $suburbIds */
    public function areas(User $user, Pro $pro, array $suburbIds): void
    {
        $suburbIds = array_values(array_unique(array_map(intval(...), $suburbIds)));

        if ($suburbIds === [] || Suburb::query()->whereKey($suburbIds)->where('is_active', true)->count() !== count($suburbIds)) {
            throw ValidationException::withMessages(['suburbIds' => __('Choose at least one suburb in our launch area.')]);
        }

        $this->change($user, $pro, 'service_areas', fn (Pro $locked) => $locked->serviceAreas()->sync($suburbIds), ['count' => count($suburbIds)]);
    }

    /**
     * @param  callable(Pro): mixed  $apply
     * @param  array<string, mixed>  $properties
     */
    private function change(User $user, Pro $pro, string $field, callable $apply, array $properties = []): void
    {
        abort_unless($pro->user_id === $user->id, 403);

        DB::transaction(function () use ($user, $pro, $field, $apply, $properties): void {
            $locked = Pro::query()->lockForUpdate()->findOrFail($pro->id);
            abort_unless($locked->status === ProStatus::Approved, 403);

            $apply($locked);
            activity()->performedOn($locked)->causedBy($user)->withProperties(['field' => $field, ...$properties])->log('pro profile edited');
        });
    }
}
