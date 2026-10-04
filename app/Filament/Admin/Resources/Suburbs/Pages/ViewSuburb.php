<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Pages;

use App\Filament\Admin\Resources\Suburbs\Concerns\MapsCentroid;
use App\Filament\Admin\Resources\Suburbs\SuburbResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewSuburb extends ViewRecord
{
    use MapsCentroid;

    protected static string $resource = SuburbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
