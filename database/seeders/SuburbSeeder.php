<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Properties\Enums\Region;
use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

/**
 * Launch-area suburbs (docs/product/launch-area.md, spec 004). Centre points
 * are approximate (≈1 km) and editable in the admin panel. Only Berea/central
 * and North start active (founder decision, Q9). Re-running only adds new suburbs.
 */
final class SuburbSeeder extends Seeder
{
    /** @var list<array{slug: string, name: string, region: Region, lat: float, lng: float}> */
    private const array SUBURBS = [
        ['slug' => 'morningside', 'name' => 'Morningside', 'region' => Region::BereaCentral, 'lat' => -29.8227, 'lng' => 31.0094],
        ['slug' => 'musgrave', 'name' => 'Musgrave', 'region' => Region::BereaCentral, 'lat' => -29.8417, 'lng' => 31.0025],
        ['slug' => 'berea', 'name' => 'Berea', 'region' => Region::BereaCentral, 'lat' => -29.8481, 'lng' => 31.0006],
        ['slug' => 'glenwood', 'name' => 'Glenwood', 'region' => Region::BereaCentral, 'lat' => -29.8700, 'lng' => 30.9950],
        ['slug' => 'durban_north', 'name' => 'Durban North', 'region' => Region::North, 'lat' => -29.7888, 'lng' => 31.0329],
        ['slug' => 'umhlanga', 'name' => 'Umhlanga', 'region' => Region::North, 'lat' => -29.7265, 'lng' => 31.0849],
        ['slug' => 'la_lucia', 'name' => 'La Lucia', 'region' => Region::North, 'lat' => -29.7577, 'lng' => 31.0600],
        ['slug' => 'westville', 'name' => 'Westville', 'region' => Region::West, 'lat' => -29.8303, 'lng' => 30.9314],
        ['slug' => 'pinetown', 'name' => 'Pinetown', 'region' => Region::West, 'lat' => -29.8167, 'lng' => 30.8580],
        ['slug' => 'kloof', 'name' => 'Kloof', 'region' => Region::West, 'lat' => -29.7833, 'lng' => 30.8333],
        ['slug' => 'amanzimtoti', 'name' => 'Amanzimtoti', 'region' => Region::South, 'lat' => -30.0500, 'lng' => 30.8833],
        ['slug' => 'bluff', 'name' => 'Bluff', 'region' => Region::South, 'lat' => -29.9330, 'lng' => 31.0160],
    ];

    /** @var list<Region> */
    private const array ACTIVE_REGIONS = [Region::BereaCentral, Region::North];

    public function run(): void
    {
        foreach (self::SUBURBS as $suburb) {
            $municipality = (string) config('sortd.places.municipality');
            $exists = Suburb::query()
                ->where('slug', $suburb['slug'])
                ->orWhere(fn ($query) => $query->where('municipality', $municipality)->where('name', $suburb['name']))
                ->exists();

            if ($exists) {
                continue;
            }

            Suburb::query()->create([
                'slug' => $suburb['slug'],
                'name' => $suburb['name'],
                'region' => $suburb['region'],
                'municipality' => $municipality,
                'centroid' => Point::makeGeodetic($suburb['lat'], $suburb['lng']),
                'is_active' => in_array($suburb['region'], self::ACTIVE_REGIONS, true),
            ]);
        }
    }
}
