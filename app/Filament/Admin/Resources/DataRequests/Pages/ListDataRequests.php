<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DataRequests\Pages;

use App\Filament\Admin\Resources\DataRequests\DataRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListDataRequests extends ListRecords
{
    protected static string $resource = DataRequestResource::class;
}
