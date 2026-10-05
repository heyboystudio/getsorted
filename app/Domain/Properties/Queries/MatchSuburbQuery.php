<?php

declare(strict_types=1);

namespace App\Domain\Properties\Queries;

use App\Contracts\Data\GeocodedAddress;
use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Str;

/**
 * The Sortd suburb for a looked-up address (spec 015, AC4): first by Google's
 * area names against suburb names and admin aliases, then the nearest suburb
 * centre within 5 km. An address Google places in eThekwini whose area we
 * don't know yet adds that suburb, so the whole of Durban is covered (decision 044).
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

        if ($this->inMunicipality($address) && $names !== []) {
            return $this->addSuburb($address, trim((string) $names[0]));
        }

        return $this->nearest($address, self::NEAREST_WITHIN_METRES);
    }

    private function nearest(GeocodedAddress $address, ?int $withinMetres): ?Suburb
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $query = Suburb::query();

        if ($withinMetres !== null) {
            $query->whereRaw("ST_DWithin(centroid, {$point}, ?)", [$address->longitude, $address->latitude, $withinMetres]);
        }

        return $query->orderByRaw("ST_Distance(centroid, {$point})", [$address->longitude, $address->latitude])->first();
    }

    private function inMunicipality(GeocodedAddress $address): bool
    {
        $municipality = (string) config('sortd.places.municipality');

        return $address->municipality !== null && str_contains(mb_strtolower($address->municipality), mb_strtolower($municipality));
    }

    /** A new active suburb at the address, in the region of the nearest known suburb. */
    private function addSuburb(GeocodedAddress $address, string $name): ?Suburb
    {
        $nearest = $this->nearest($address, null);

        // "Durban" alone is the city, not a suburb: use the nearest one.
        if ($name === '' || mb_strlen($name) > 100 || mb_strtolower($name) === 'durban' || ! $nearest instanceof Suburb) {
            return $nearest;
        }

        $slug = Str::slug($name, '_');
        $slug = Suburb::query()->where('slug', $slug)->exists() ? $slug.'_'.Str::lower(Str::random(4)) : $slug;

        $suburb = Suburb::query()->firstOrCreate(
            ['municipality' => config('sortd.places.municipality'), 'name' => $name],
            [
                'slug' => $slug,
                'region' => $nearest->region,
                'centroid' => Point::makeGeodetic($address->latitude, $address->longitude),
                'is_active' => true,
            ],
        );

        activity()->performedOn($suburb)->withProperties(['source' => 'places'])->log('suburb added from address lookup');

        return $suburb;
    }
}
