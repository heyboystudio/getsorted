<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pro;
use App\Models\Trade;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

/** Explicit local-only fixture for phone walkthroughs before vetting ships. */
final class LocalCoverageSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $user = User::query()->where('phone_e164', '+27725550101')->first();

        if (! $user instanceof User) {
            return;
        }

        $pro = Pro::query()->firstOrNew(['user_id' => $user->id]);
        $pro->forceFill([
            'business_name' => 'Local demo pro', 'status' => 'approved', 'approved_at' => $pro->approved_at ?? now(),
            'base_location' => Point::makeGeodetic(-29.8587, 31.0218), 'base_area_label' => 'Durban central', 'service_radius_km' => 15,
        ])->save();
        $pro->trades()->syncWithoutDetaching(Trade::query()->where('is_active', true)->pluck('id')->all());
    }
}
