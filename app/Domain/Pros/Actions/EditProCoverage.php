<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Support\Vetting;
use App\Models\Pro;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Vetting admins correct a pro's services and suburbs; logged (spec 008, rules). */
final class EditProCoverage
{
    /**
     * @param  list<int>  $serviceIds
     * @param  list<int>  $suburbIds
     */
    public function handle(User $admin, Pro $pro, array $serviceIds, array $suburbIds): void
    {
        $serviceIds = array_values(array_unique(array_map(intval(...), $serviceIds)));
        $suburbIds = array_values(array_unique(array_map(intval(...), $suburbIds)));

        if ($serviceIds === [] || Service::query()->whereKey($serviceIds)->where('is_active', true)->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['service_ids' => __('Choose at least one active service.')]);
        }

        if ($suburbIds === [] || Suburb::query()->whereKey($suburbIds)->where('is_active', true)->count() !== count($suburbIds)) {
            throw ValidationException::withMessages(['suburb_ids' => __('Choose at least one active suburb.')]);
        }

        Vetting::locked($admin, $pro, ProStatus::cases(), function (Pro $locked) use ($admin, $serviceIds, $suburbIds): void {
            $locked->services()->sync($serviceIds);
            $locked->serviceAreas()->sync($suburbIds);
            Vetting::log($admin, $locked, 'pro_coverage_edited', ['services' => count($serviceIds), 'suburbs' => count($suburbIds)]);
        });
    }
}
