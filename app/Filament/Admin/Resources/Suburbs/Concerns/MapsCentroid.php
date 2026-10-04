<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Concerns;

use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;

/** The form edits latitude/longitude; the model stores one geography point. */
trait MapsCentroid
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $point = $record instanceof Suburb ? $record->centroid : null;

        if ($point instanceof Point) {
            $data['latitude'] = $point->getLatitude();
            $data['longitude'] = $point->getLongitude();
        }

        // Geometry objects can't travel to the browser; the form only edits lat/lng.
        unset($data['centroid'], $data['boundary']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withCentroid(array $data): array
    {
        $data['centroid'] = Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude']);
        unset($data['latitude'], $data['longitude']);

        return $data;
    }
}
