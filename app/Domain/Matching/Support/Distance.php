<?php

declare(strict_types=1);

namespace App\Domain\Matching\Support;

use Clickbar\Magellan\Data\Geometries\Point;

/** Straight-line distance between two points, for showing pros roughly how far a job is (spec 020, D-d). */
final class Distance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    public static function km(Point $from, Point $to): float
    {
        $lat1 = deg2rad($from->getLatitude());
        $lat2 = deg2rad($to->getLatitude());
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($to->getLongitude() - $from->getLongitude());
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    /** "about 6 km away": rounded so it never pinpoints the address. */
    public static function label(Point $from, Point $to): string
    {
        $km = self::km($from, $to);

        return $km < 1 ? __('less than 1 km away') : __('about :km km away', ['km' => (int) round($km)]);
    }
}
