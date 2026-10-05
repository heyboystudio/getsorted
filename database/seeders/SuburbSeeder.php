<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Properties\Enums\Region;
use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

/**
 * Launch-area suburbs (docs/product/launch-area.md, spec 004). Centre points
 * are approximate (≈1 km) and editable in the admin panel. Every suburb starts
 * active: the whole of Durban is open (decision 044). Re-running only adds new suburbs.
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
        // Whole of Durban (decision 044, 2026-10-05).
        ['slug' => 'durban_central', 'name' => 'Durban Central', 'region' => Region::BereaCentral, 'lat' => -29.8587, 'lng' => 31.0218],
        ['slug' => 'south_beach', 'name' => 'South Beach', 'region' => Region::BereaCentral, 'lat' => -29.8640, 'lng' => 31.0380],
        ['slug' => 'point', 'name' => 'Point', 'region' => Region::BereaCentral, 'lat' => -29.8680, 'lng' => 31.0450],
        ['slug' => 'greyville', 'name' => 'Greyville', 'region' => Region::BereaCentral, 'lat' => -29.8370, 'lng' => 31.0130],
        ['slug' => 'windermere', 'name' => 'Windermere', 'region' => Region::BereaCentral, 'lat' => -29.8330, 'lng' => 31.0230],
        ['slug' => 'stamford_hill', 'name' => 'Stamford Hill', 'region' => Region::BereaCentral, 'lat' => -29.8250, 'lng' => 31.0250],
        ['slug' => 'essenwood', 'name' => 'Essenwood', 'region' => Region::BereaCentral, 'lat' => -29.8370, 'lng' => 31.0050],
        ['slug' => 'overport', 'name' => 'Overport', 'region' => Region::BereaCentral, 'lat' => -29.8330, 'lng' => 30.9930],
        ['slug' => 'sydenham', 'name' => 'Sydenham', 'region' => Region::BereaCentral, 'lat' => -29.8250, 'lng' => 30.9800],
        ['slug' => 'puntans_hill', 'name' => 'Puntans Hill', 'region' => Region::BereaCentral, 'lat' => -29.8400, 'lng' => 30.9850],
        ['slug' => 'mayville', 'name' => 'Mayville', 'region' => Region::BereaCentral, 'lat' => -29.8500, 'lng' => 30.9750],
        ['slug' => 'cato_manor', 'name' => 'Cato Manor', 'region' => Region::BereaCentral, 'lat' => -29.8580, 'lng' => 30.9700],
        ['slug' => 'umbilo', 'name' => 'Umbilo', 'region' => Region::BereaCentral, 'lat' => -29.8780, 'lng' => 30.9900],
        ['slug' => 'bulwer', 'name' => 'Bulwer', 'region' => Region::BereaCentral, 'lat' => -29.8620, 'lng' => 31.0000],
        ['slug' => 'congella', 'name' => 'Congella', 'region' => Region::BereaCentral, 'lat' => -29.8780, 'lng' => 31.0050],
        ['slug' => 'sherwood', 'name' => 'Sherwood', 'region' => Region::BereaCentral, 'lat' => -29.8450, 'lng' => 30.9650],
        ['slug' => 'bellair', 'name' => 'Bellair', 'region' => Region::BereaCentral, 'lat' => -29.8780, 'lng' => 30.9700],
        ['slug' => 'sea_view', 'name' => 'Sea View', 'region' => Region::BereaCentral, 'lat' => -29.8880, 'lng' => 30.9650],
        ['slug' => 'springfield', 'name' => 'Springfield', 'region' => Region::BereaCentral, 'lat' => -29.8150, 'lng' => 30.9850],
        ['slug' => 'umgeni_park', 'name' => 'Umgeni Park', 'region' => Region::North, 'lat' => -29.8000, 'lng' => 31.0250],
        ['slug' => 'glenashley', 'name' => 'Glenashley', 'region' => Region::North, 'lat' => -29.7700, 'lng' => 31.0550],
        ['slug' => 'virginia', 'name' => 'Virginia', 'region' => Region::North, 'lat' => -29.7730, 'lng' => 31.0450],
        ['slug' => 'prospect_hall', 'name' => 'Prospect Hall', 'region' => Region::North, 'lat' => -29.7900, 'lng' => 31.0200],
        ['slug' => 'red_hill', 'name' => 'Red Hill', 'region' => Region::North, 'lat' => -29.7850, 'lng' => 31.0050],
        ['slug' => 'greenwood_park', 'name' => 'Greenwood Park', 'region' => Region::North, 'lat' => -29.7780, 'lng' => 31.0000],
        ['slug' => 'avoca', 'name' => 'Avoca', 'region' => Region::North, 'lat' => -29.7750, 'lng' => 30.9950],
        ['slug' => 'effingham', 'name' => 'Effingham', 'region' => Region::North, 'lat' => -29.7850, 'lng' => 30.9900],
        ['slug' => 'parlock', 'name' => 'Parlock', 'region' => Region::North, 'lat' => -29.7800, 'lng' => 30.9850],
        ['slug' => 'newlands', 'name' => 'Newlands', 'region' => Region::North, 'lat' => -29.7700, 'lng' => 30.9700],
        ['slug' => 'phoenix', 'name' => 'Phoenix', 'region' => Region::North, 'lat' => -29.7000, 'lng' => 30.9800],
        ['slug' => 'mount_edgecombe', 'name' => 'Mount Edgecombe', 'region' => Region::North, 'lat' => -29.7050, 'lng' => 31.0200],
        ['slug' => 'umhlanga_ridge', 'name' => 'Umhlanga Ridge', 'region' => Region::North, 'lat' => -29.7280, 'lng' => 31.0650],
        ['slug' => 'la_lucia_ridge', 'name' => 'La Lucia Ridge', 'region' => Region::North, 'lat' => -29.7480, 'lng' => 31.0480],
        ['slug' => 'sunningdale', 'name' => 'Sunningdale', 'region' => Region::North, 'lat' => -29.7400, 'lng' => 31.0400],
        ['slug' => 'glen_hills', 'name' => 'Glen Hills', 'region' => Region::North, 'lat' => -29.7600, 'lng' => 31.0350],
        ['slug' => 'umdloti', 'name' => 'Umdloti', 'region' => Region::North, 'lat' => -29.6700, 'lng' => 31.1100],
        ['slug' => 'verulam', 'name' => 'Verulam', 'region' => Region::North, 'lat' => -29.6450, 'lng' => 31.0450],
        ['slug' => 'tongaat', 'name' => 'Tongaat', 'region' => Region::North, 'lat' => -29.5700, 'lng' => 31.1200],
        ['slug' => 'la_mercy', 'name' => 'La Mercy', 'region' => Region::North, 'lat' => -29.6250, 'lng' => 31.1300],
        ['slug' => 'westville_north', 'name' => 'Westville North', 'region' => Region::West, 'lat' => -29.8150, 'lng' => 30.9300],
        ['slug' => 'cowies_hill', 'name' => 'Cowies Hill', 'region' => Region::West, 'lat' => -29.8300, 'lng' => 30.8900],
        ['slug' => 'new_germany', 'name' => 'New Germany', 'region' => Region::West, 'lat' => -29.8000, 'lng' => 30.8800],
        ['slug' => 'queensburgh', 'name' => 'Queensburgh', 'region' => Region::West, 'lat' => -29.8700, 'lng' => 30.9300],
        ['slug' => 'malvern', 'name' => 'Malvern', 'region' => Region::West, 'lat' => -29.8800, 'lng' => 30.9200],
        ['slug' => 'escombe', 'name' => 'Escombe', 'region' => Region::West, 'lat' => -29.8700, 'lng' => 30.9450],
        ['slug' => 'sarnia', 'name' => 'Sarnia', 'region' => Region::West, 'lat' => -29.8450, 'lng' => 30.8750],
        ['slug' => 'hillcrest', 'name' => 'Hillcrest', 'region' => Region::West, 'lat' => -29.7800, 'lng' => 30.7600],
        ['slug' => 'gillitts', 'name' => 'Gillitts', 'region' => Region::West, 'lat' => -29.7900, 'lng' => 30.7950],
        ['slug' => 'waterfall', 'name' => 'Waterfall', 'region' => Region::West, 'lat' => -29.7500, 'lng' => 30.7900],
        ['slug' => 'forest_hills', 'name' => 'Forest Hills', 'region' => Region::West, 'lat' => -29.7600, 'lng' => 30.7700],
        ['slug' => 'winston_park', 'name' => 'Winston Park', 'region' => Region::West, 'lat' => -29.7800, 'lng' => 30.7750],
        ['slug' => 'bothas_hill', 'name' => "Botha's Hill", 'region' => Region::West, 'lat' => -29.7550, 'lng' => 30.7300],
        ['slug' => 'everton', 'name' => 'Everton', 'region' => Region::West, 'lat' => -29.7900, 'lng' => 30.8100],
        ['slug' => 'clermont', 'name' => 'Clermont', 'region' => Region::West, 'lat' => -29.8100, 'lng' => 30.8900],
        ['slug' => 'mariannhill', 'name' => 'Mariannhill', 'region' => Region::West, 'lat' => -29.8450, 'lng' => 30.8350],
        ['slug' => 'montclair', 'name' => 'Montclair', 'region' => Region::South, 'lat' => -29.9150, 'lng' => 30.9700],
        ['slug' => 'woodlands', 'name' => 'Woodlands', 'region' => Region::South, 'lat' => -29.9250, 'lng' => 30.9600],
        ['slug' => 'yellowwood_park', 'name' => 'Yellowwood Park', 'region' => Region::South, 'lat' => -29.9200, 'lng' => 30.9400],
        ['slug' => 'wentworth', 'name' => 'Wentworth', 'region' => Region::South, 'lat' => -29.9350, 'lng' => 31.0000],
        ['slug' => 'brighton_beach', 'name' => 'Brighton Beach', 'region' => Region::South, 'lat' => -29.9350, 'lng' => 31.0150],
        ['slug' => 'fynnland', 'name' => 'Fynnland', 'region' => Region::South, 'lat' => -29.9150, 'lng' => 31.0250],
        ['slug' => 'clairwood', 'name' => 'Clairwood', 'region' => Region::South, 'lat' => -29.9100, 'lng' => 30.9850],
        ['slug' => 'merebank', 'name' => 'Merebank', 'region' => Region::South, 'lat' => -29.9550, 'lng' => 30.9900],
        ['slug' => 'chatsworth', 'name' => 'Chatsworth', 'region' => Region::South, 'lat' => -29.9100, 'lng' => 30.8900],
        ['slug' => 'umlazi', 'name' => 'Umlazi', 'region' => Region::South, 'lat' => -29.9700, 'lng' => 30.8900],
        ['slug' => 'isipingo', 'name' => 'Isipingo', 'region' => Region::South, 'lat' => -29.9950, 'lng' => 30.9350],
        ['slug' => 'lamontville', 'name' => 'Lamontville', 'region' => Region::South, 'lat' => -29.9450, 'lng' => 30.9500],
        ['slug' => 'athlone_park', 'name' => 'Athlone Park', 'region' => Region::South, 'lat' => -30.0150, 'lng' => 30.9100],
        ['slug' => 'doonside', 'name' => 'Doonside', 'region' => Region::South, 'lat' => -30.0700, 'lng' => 30.8650],
        ['slug' => 'warner_beach', 'name' => 'Warner Beach', 'region' => Region::South, 'lat' => -30.0850, 'lng' => 30.8550],
        ['slug' => 'winklespruit', 'name' => 'Winklespruit', 'region' => Region::South, 'lat' => -30.0950, 'lng' => 30.8450],
        ['slug' => 'kingsburgh', 'name' => 'Kingsburgh', 'region' => Region::South, 'lat' => -30.0750, 'lng' => 30.8600],
        ['slug' => 'illovo_beach', 'name' => 'Illovo Beach', 'region' => Region::South, 'lat' => -30.1100, 'lng' => 30.8350],
        ['slug' => 'umkomaas', 'name' => 'Umkomaas', 'region' => Region::South, 'lat' => -30.2050, 'lng' => 30.8000],
    ];

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
                'is_active' => true,
            ]);
        }
    }
}
