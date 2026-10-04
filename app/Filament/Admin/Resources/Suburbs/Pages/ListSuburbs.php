<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Pages;

use App\Filament\Admin\Resources\Suburbs\SuburbResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSuburbs extends ListRecords
{
    protected static string $resource = SuburbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
