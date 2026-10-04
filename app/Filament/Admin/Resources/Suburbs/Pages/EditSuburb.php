<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Pages;

use App\Filament\Admin\Resources\Suburbs\Concerns\MapsCentroid;
use App\Filament\Admin\Resources\Suburbs\SuburbResource;
use App\Models\Suburb;
use Clickbar\Magellan\Data\Geometries\Point;
use Filament\Resources\Pages\EditRecord;

final class EditSuburb extends EditRecord
{
    use MapsCentroid;

    protected static string $resource = SuburbResource::class;

    private ?Point $originalCentroid = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withCentroid($data);
    }

    /** Geometry isn't captured by the model's audit options, so centre moves are logged here. */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Suburb || ! $record->wasChanged('centroid')) {
            return;
        }

        $old = $this->originalCentroid;
        $new = $record->centroid;

        activity()->performedOn($record)->causedBy(auth()->user())->event('updated')
            ->withProperties([
                'old' => $old instanceof Point ? ['latitude' => $old->getLatitude(), 'longitude' => $old->getLongitude()] : null,
                'attributes' => ['latitude' => $new->getLatitude(), 'longitude' => $new->getLongitude()],
            ])
            ->log('suburb centre moved');
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $this->originalCentroid = $record instanceof Suburb ? $record->centroid : null;
    }
}
