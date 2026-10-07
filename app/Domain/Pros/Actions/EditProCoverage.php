<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Support\Vetting;
use App\Models\Pro;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Vetting admins correct a pro's trades and travel radius; logged (spec 008, spec 020). */
final class EditProCoverage
{
    /** @param  list<int>  $tradeIds */
    public function handle(User $admin, Pro $pro, array $tradeIds, int $radiusKm): void
    {
        $tradeIds = array_values(array_unique(array_map(intval(...), $tradeIds)));

        if ($tradeIds === [] || Trade::query()->whereKey($tradeIds)->where('is_active', true)->count() !== count($tradeIds)) {
            throw ValidationException::withMessages(['trade_ids' => __('Choose at least one active trade.')]);
        }

        if ($radiusKm < 1 || $radiusKm > 50) {
            throw ValidationException::withMessages(['radius_km' => __('Choose a distance between 1 and 50 km.')]);
        }

        Vetting::locked($admin, $pro, ProStatus::cases(), function (Pro $locked) use ($admin, $tradeIds, $radiusKm): void {
            $locked->trades()->sync($tradeIds);
            $locked->forceFill(['service_radius_km' => $radiusKm])->save();
            Vetting::log($admin, $locked, 'pro_coverage_edited', ['trades' => count($tradeIds), 'radius_km' => $radiusKm]);
        });
    }
}
