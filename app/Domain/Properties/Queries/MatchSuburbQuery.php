<?php

declare(strict_types=1);

namespace App\Domain\Properties\Queries;

use App\Contracts\Data\GeocodedAddress;
use App\Models\Suburb;

/**
 * The Sortd suburb for a looked-up address (spec 015, AC4): first by Google's
 * area names against suburb names and admin aliases, then the nearest suburb
 * centre within 5 km.
 */
final class MatchSuburbQuery
{
    private const int NEAREST_WITHIN_METRES = 5000;

    public function handle(GeocodedAddress $address): ?Suburb
    {
        $names = $address->areaNames !== [] ? $address->areaNames : array_filter([$address->suburb]);

        foreach ($names as $name) {
            $name = mb_strtolower(trim((string) $name));

            if ($name === '') {
                continue;
            }

            $suburb = Suburb::query()
                ->where(function ($query) use ($name): void {
                    $query->whereRaw('lower(name) = ?', [$name])
                        ->orWhereRaw('exists (select 1 from jsonb_array_elements_text(aliases) alias where lower(alias) = ?)', [$name])
                        // "Umhlanga Rocks" is Umhlanga.
                        ->orWhereRaw("? like lower(name) || ' %'", [$name]);
                })
                ->orderByDesc('is_active')
                ->orderByRaw('length(name) desc')
                ->first();

            if ($suburb instanceof Suburb) {
                return $suburb;
            }
        }

        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return Suburb::query()
            ->whereRaw("ST_DWithin(centroid, {$point}, ?)", [$address->longitude, $address->latitude, self::NEAREST_WITHIN_METRES])
            ->orderByRaw("ST_Distance(centroid, {$point})", [$address->longitude, $address->latitude])
            ->first();
    }
}
