<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Pages;

use App\Filament\Admin\Resources\Suburbs\Concerns\MapsCentroid;
use App\Filament\Admin\Resources\Suburbs\SuburbResource;
use Filament\Resources\Pages\EditRecord;

final class EditSuburb extends EditRecord
{
    use MapsCentroid;

    protected static string $resource = SuburbResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withCentroid($data);
    }
}
