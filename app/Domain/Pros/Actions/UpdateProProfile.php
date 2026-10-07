<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * An approved pro edits the parts of their profile that need no review (spec 021, AC26): bio,
 * weekly job cap, and service suburbs inside the launch area. Services and registrations go
 * through review instead. Every change is logged.
 */
final readonly class UpdateProProfile
{
    public const int MAX_WEEKLY_CAP = 50;

    public function __construct(private SaveApplicationStep $steps) {}

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

    /**
     * Where the pro works from and how far they travel. A new address comes from Google Places;
     * without one only the radius changes. Applies at once, with the same rules as the application.
     */
    public function workArea(User $user, Pro $pro, ?GeocodedAddress $address, ?string $placeId, int $radiusKm): void
    {
        if ($address instanceof GeocodedAddress && $placeId !== null) {
            $this->steps->validBase($address, $radiusKm);
            $this->change($user, $pro, 'work_area', fn (Pro $locked) => $this->steps->writeBase($locked, $address, $placeId, $radiusKm), ['radius_km' => $radiusKm, 'moved' => true]);

            return;
        }

        $this->steps->validRadius($radiusKm);
        $this->change($user, $pro, 'work_area', fn (Pro $locked) => $locked->forceFill(['service_radius_km' => $radiusKm])->save(), ['radius_km' => $radiusKm, 'moved' => false]);
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
